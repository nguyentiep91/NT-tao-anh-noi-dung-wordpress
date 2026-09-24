( function () {
	'use strict';

	const config = window.NTContentImagesContent;
	const apiFetch = window.wp && window.wp.apiFetch;
	if ( ! config || ! apiFetch ) {
		return;
	}
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );

	const candidatesBody = document.querySelector( '#ntci-content-candidates' );
	const feedback = document.querySelector( '#ntci-content-feedback' );
	const refresh = document.querySelector( '#ntci-content-refresh' );
	const detail = document.querySelector( '#ntci-content-detail' );
	const detailTitle = document.querySelector( '#ntci-content-detail-title' );
	const detailMeta = document.querySelector( '#ntci-content-detail-meta' );
	const slotsWrap = document.querySelector( '#ntci-content-slots' );
	const generateButton = document.querySelector( '#ntci-content-generate' );
	const insertButton = document.querySelector( '#ntci-content-insert' );
	const rollbackButton = document.querySelector( '#ntci-content-rollback' );
	let busy = false;
	let currentPostId = 0;

	function request( path, method, data ) {
		return apiFetch( { path: config.root + path, method: method || 'GET', data: data } );
	}

	function escapeHtml( value ) {
		return String( value || '' ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	}

	function setFeedback( message, type ) {
		if ( feedback ) {
			feedback.textContent = message || '';
			feedback.className = 'ntci-content-feedback' + ( type ? ' ntci-content-feedback--' + type : '' );
		}
	}

	async function loadCandidates() {
		candidatesBody.innerHTML = '<tr><td colspan="5">Đang tải…</td></tr>';
		try {
			const data = await request( '/content-images/candidates?limit=30' );
			if ( ! data.items || ! data.items.length ) {
				candidatesBody.innerHTML = '<tr><td colspan="5">Chưa có bài nào được audit. Hãy chạy Kiểm tra nội dung trước.</td></tr>';
				return;
			}
			candidatesBody.innerHTML = data.items.map( function ( item ) {
				return '<tr><td><strong>' + escapeHtml( item.title ) + '</strong><br><code>#' + Number( item.post_id ) + ' · ' + escapeHtml( item.post_type ) + '</code></td><td>' + Number( item.word_count || 0 ).toLocaleString() + '</td><td>' + Number( item.planned_images ) + ' ảnh</td><td>' + Number( item.content_images ) + '</td><td><button type="button" class="button ntci-content-open" data-post-id="' + Number( item.post_id ) + '">Xem kế hoạch</button></td></tr>';
			} ).join( '' );
		} catch ( error ) {
			candidatesBody.innerHTML = '<tr><td colspan="5">Không thể tải danh sách.</td></tr>';
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	function placementLabel( placement ) {
		if ( ! placement || ! placement.type ) {
			return 'Không xác định';
		}
		if ( placement.type === 'after_intro' ) {
			return 'Sau phần mở đầu (điểm ngắt thị giác đầu tiên)';
		}
		if ( placement.type === 'before_heading' ) {
			return 'Trước mục: “' + ( placement.heading_text || '' ) + '”';
		}
		return 'Cần chèn thủ công (' + ( placement.reason || placement.type ) + ')';
	}

	function statusLabel( status ) {
		const labels = { generated: 'Chờ duyệt', approved: 'Đã duyệt — chờ chèn', rejected: 'Đã từ chối', failed: 'Lỗi', inserted: 'Đã chèn vào bài' };
		return labels[ status ] || status;
	}

	let currentHeadings = [];

	function headingOptions( selected, includeAuto ) {
		const auto = includeAuto ? '<option value="">— Theo kế hoạch tự động —</option>' : '';
		return auto + currentHeadings.map( function ( heading ) {
			const sel = heading.text === selected ? ' selected' : '';
			return '<option value="' + escapeHtml( heading.text ) + '"' + sel + '>H' + Number( heading.level ) + ': ' + escapeHtml( heading.text ) + '</option>';
		} ).join( '' );
	}

	function renderSlots( plan ) {
		currentHeadings = plan.headings || [];
		const cards = plan.slots.map( function ( slot ) {
			const record = slot.record;
			const index = Number( slot.index );
			let body = '';
			if ( ! slot.enabled ) {
				body = '<div class="ntci-content-placeholder">Đã tắt — plugin bỏ qua vị trí này khi tạo và chèn ảnh</div>';
			} else if ( record ) {
				const image = record.image_url ? '<img src="' + escapeHtml( record.image_url ) + '" alt="">' : '<div class="ntci-content-placeholder">Chưa có ảnh</div>';
				let actions = '';
				if ( record.status === 'generated' ) {
					actions = '<button type="button" class="button button-primary ntci-content-approve" data-id="' + Number( record.id ) + '">Duyệt</button> <button type="button" class="button ntci-content-reject" data-id="' + Number( record.id ) + '">Từ chối</button>';
				} else if ( record.status === 'approved' ) {
					actions = '<button type="button" class="button ntci-content-reject" data-id="' + Number( record.id ) + '">Bỏ duyệt</button>';
				}
				body = image + '<p><span class="ntci-content-badge ntci-content-badge--' + escapeHtml( record.status ) + '">' + escapeHtml( statusLabel( record.status ) ) + '</span></p><p class="ntci-content-slot-actions">' + actions + '</p>';
			} else {
				body = '<div class="ntci-content-placeholder">' + ( slot.safe ? 'Chưa tạo ảnh cho vị trí này' : 'Vị trí cần xử lý thủ công — plugin sẽ không tự chèn' ) + '</div><p><button type="button" class="button ntci-content-generate-one" data-index="' + index + '" ' + ( slot.safe ? '' : 'disabled' ) + '>Tạo ảnh vị trí này</button></p>';
			}
			const flags = ( slot.user_modified ? ' <span class="ntci-content-badge">✎ đã tuỳ chỉnh</span>' : '' ) + ( slot.is_extra ? ' <span class="ntci-content-badge">thêm tay</span>' : '' );
			const effectiveHeading = slot.placement && slot.placement.type === 'before_heading' ? ( slot.placement.heading_text || '' ) : '';
			let editor = '<div class="ntci-plan-editor" style="border-top:1px solid #e2e8f0;margin-top:8px;padding-top:8px;">';
			editor += '<label style="display:block;margin-bottom:6px;">Vị trí chèn: <select class="ntci-plan-heading" data-index="' + index + '">' + headingOptions( effectiveHeading, ! slot.is_extra ) + '</select></label>';
			editor += '<label style="display:block;margin-bottom:6px;">Mô tả cảnh cho AI (để trống = tự động theo mục):<br><textarea class="ntci-plan-scene" data-index="' + index + '" rows="3" style="width:100%;" placeholder="Ví dụ: Hai kỹ sư trao đổi trước màn hình máy tính hiển thị bản vẽ công trình, ánh sáng tự nhiên…">' + escapeHtml( slot.custom_scene || '' ) + '</textarea></label>';
			editor += '<p class="ntci-content-slot-actions">'
				+ '<button type="button" class="button ntci-plan-save-scene" data-index="' + index + '">Lưu mô tả</button> '
				+ '<button type="button" class="button ntci-plan-toggle" data-index="' + index + '" data-enabled="' + ( slot.enabled ? '0' : '1' ) + '">' + ( slot.enabled ? 'Tắt vị trí này' : 'Bật lại vị trí này' ) + '</button>'
				+ ( slot.is_extra ? ' <button type="button" class="button ntci-plan-remove-extra" data-index="' + index + '">Xoá vị trí</button>' : '' )
				+ '</p></div>';
			return '<article class="ntci-content-slot"' + ( slot.enabled ? '' : ' style="opacity:.55"' ) + '><header><strong>Ảnh ' + index + '</strong> — ' + escapeHtml( placementLabel( slot.placement ) ) + flags + '<br><small>' + escapeHtml( slot.purpose || '' ) + '</small></header>' + body + editor + '</article>';
		} ).join( '' );

		let footer = '<div class="ntci-plan-footer" style="margin-top:14px;">';
		if ( currentHeadings.length ) {
			footer += '<label>Thêm ảnh tại mục: <select id="ntci-plan-add-heading">' + headingOptions( '', false ) + '</select></label> <button type="button" class="button" id="ntci-plan-add">+ Thêm vị trí ảnh</button> ';
		}
		if ( plan.has_overrides ) {
			footer += '<button type="button" class="button" id="ntci-plan-reset" style="color:#b32d2e;">Xoá mọi tuỳ chỉnh, về kế hoạch tự động</button>';
		}
		footer += '</div>';
		slotsWrap.innerHTML = cards + footer;
	}

	async function planOverride( data, message ) {
		await act( message || 'Đang lưu tuỳ chỉnh kế hoạch…', async function () {
			const plan = await request( '/content-images/plan-override', 'POST', Object.assign( { post_id: currentPostId }, data ) );
			renderSlots( plan );
			setFeedback( 'Đã lưu tuỳ chỉnh kế hoạch.', 'success' );
		} );
	}

	async function openPlan( postId ) {
		currentPostId = Number( postId );
		detail.hidden = false;
		slotsWrap.innerHTML = '<p>Đang tải kế hoạch…</p>';
		try {
			const plan = await request( '/content-images/plan?post_id=' + currentPostId );
			detailTitle.textContent = plan.title + ' (#' + plan.post_id + ')';
			const safeCount = plan.slots.filter( function ( slot ) { return slot.safe; } ).length;
			detailMeta.innerHTML = 'Kế hoạch: ' + plan.slots.length + ' ảnh trong nội dung (' + safeCount + ' vị trí an toàn) · <a href="' + escapeHtml( plan.edit_url ) + '" target="_blank" rel="noopener noreferrer">Sửa bài</a> · <a href="' + escapeHtml( plan.view_url ) + '" target="_blank" rel="noopener noreferrer">Xem bài</a>';
			rollbackButton.hidden = ! plan.snapshot;
			renderSlots( plan );
			detail.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} catch ( error ) {
			slotsWrap.innerHTML = '';
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	async function act( label, work ) {
		if ( busy ) {
			return;
		}
		busy = true;
		setFeedback( label );
		try {
			await work();
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		} finally {
			busy = false;
		}
	}

	document.addEventListener( 'change', function ( event ) {
		const headingSelect = event.target.closest( '.ntci-plan-heading' );
		if ( headingSelect ) {
			planOverride( { index: Number( headingSelect.dataset.index ), heading_text: headingSelect.value }, 'Đang đổi vị trí chèn…' );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		const open = event.target.closest( '.ntci-content-open' );
		const approve = event.target.closest( '.ntci-content-approve' );
		const reject = event.target.closest( '.ntci-content-reject' );
		const generateOne = event.target.closest( '.ntci-content-generate-one' );
		if ( open ) {
			openPlan( open.dataset.postId );
		} else if ( approve ) {
			act( 'Đang duyệt ảnh…', async function () {
				await request( '/generations/' + Number( approve.dataset.id ) + '/approve', 'POST', {} );
				setFeedback( 'Đã duyệt. Ảnh sẵn sàng để chèn vào bài.', 'success' );
				await openPlan( currentPostId );
			} );
		} else if ( reject ) {
			act( 'Đang cập nhật…', async function () {
				await request( '/generations/' + Number( reject.dataset.id ) + '/reject', 'POST', {} );
				setFeedback( 'Đã từ chối ảnh.', 'success' );
				await openPlan( currentPostId );
			} );
		} else if ( generateOne ) {
			if ( ! window.confirm( config.labels.confirmGenerate ) ) {
				return;
			}
			act( 'Đang tạo ảnh cho vị trí ' + generateOne.dataset.index + '… (30–90 giây)', async function () {
				await request( '/content-images/generate', 'POST', { post_id: currentPostId, index: Number( generateOne.dataset.index ) } );
				setFeedback( 'Đã tạo ảnh. Hãy duyệt để chèn vào bài.', 'success' );
				await openPlan( currentPostId );
			} );
		} else if ( event.target.closest( '.ntci-plan-toggle' ) ) {
			const toggle = event.target.closest( '.ntci-plan-toggle' );
			planOverride( { index: Number( toggle.dataset.index ), enabled: toggle.dataset.enabled === '1' } );
		} else if ( event.target.closest( '.ntci-plan-save-scene' ) ) {
			const saveScene = event.target.closest( '.ntci-plan-save-scene' );
			const textarea = document.querySelector( '.ntci-plan-scene[data-index="' + saveScene.dataset.index + '"]' );
			planOverride( { index: Number( saveScene.dataset.index ), custom_scene: textarea ? textarea.value : '' }, 'Đang lưu mô tả cảnh…' );
		} else if ( event.target.closest( '.ntci-plan-remove-extra' ) ) {
			const removeExtra = event.target.closest( '.ntci-plan-remove-extra' );
			planOverride( { remove_extra: Number( removeExtra.dataset.index ) }, 'Đang xoá vị trí…' );
		} else if ( event.target.closest( '#ntci-plan-add' ) ) {
			const select = document.querySelector( '#ntci-plan-add-heading' );
			if ( select && select.value ) {
				planOverride( { add_heading: select.value }, 'Đang thêm vị trí ảnh…' );
			}
		} else if ( event.target.closest( '#ntci-plan-reset' ) ) {
			if ( window.confirm( 'Xoá mọi tuỳ chỉnh và quay về kế hoạch tự động 100%?' ) ) {
				planOverride( { reset: true }, 'Đang khôi phục kế hoạch tự động…' );
			}
		}
	} );

	if ( generateButton ) {
		generateButton.addEventListener( 'click', function () {
			if ( ! currentPostId || ! window.confirm( config.labels.confirmGenerate ) ) {
				return;
			}
			act( 'Đang tạo bộ ảnh theo kế hoạch… Mỗi ảnh mất 30–90 giây, vui lòng chờ.', async function () {
				const result = await request( '/content-images/generate', 'POST', { post_id: currentPostId } );
				const generated = ( result.generated || [] ).length;
				const errors = ( result.errors || [] ).length;
				setFeedback( 'Đã tạo ' + generated + ' ảnh' + ( errors ? ', ' + errors + ' lỗi' : '' ) + '. Hãy duyệt từng ảnh rồi bấm Chèn.', errors ? 'error' : 'success' );
				await openPlan( currentPostId );
			} );
		} );
	}
	if ( insertButton ) {
		insertButton.addEventListener( 'click', function () {
			if ( ! currentPostId || ! window.confirm( config.labels.confirmInsert ) ) {
				return;
			}
			act( 'Đang chèn ảnh vào bài…', async function () {
				const result = await request( '/content-images/insert', 'POST', { post_id: currentPostId } );
				const skipped = ( result.skipped || [] ).length;
				setFeedback( 'Đã chèn ' + ( result.inserted || [] ).length + ' ảnh vào bài' + ( skipped ? ' (' + skipped + ' vị trí bị bỏ qua)' : '' ) + '. Xem bài để kiểm tra.', 'success' );
				await openPlan( currentPostId );
			} );
		} );
	}
	if ( rollbackButton ) {
		rollbackButton.addEventListener( 'click', function () {
			if ( ! currentPostId || ! window.confirm( config.labels.confirmRollback ) ) {
				return;
			}
			act( 'Đang khôi phục nội dung…', async function () {
				await request( '/content-images/rollback', 'POST', { post_id: currentPostId } );
				setFeedback( 'Đã khôi phục bài viết về trạng thái trước khi chèn.', 'success' );
				await openPlan( currentPostId );
			} );
		} );
	}
	if ( refresh ) {
		refresh.addEventListener( 'click', loadCandidates );
	}

	// ---- Batch queue ----
	const queueStart = document.querySelector( '#ntci-queue-start' );
	const queuePause = document.querySelector( '#ntci-queue-pause' );
	const queueResume = document.querySelector( '#ntci-queue-resume' );
	const queueCancel = document.querySelector( '#ntci-queue-cancel' );
	const queueProgress = document.querySelector( '#ntci-queue-progress' );
	const queueBarFill = document.querySelector( '#ntci-queue-bar-fill' );
	const queueSummary = document.querySelector( '#ntci-queue-summary' );
	const queueItems = document.querySelector( '#ntci-queue-items' );
	const queueUsage = document.querySelector( '#ntci-queue-usage' );
	let queueLoopActive = false;

	function taskLabel( state ) {
		const labels = { pending: '⏳', done: '✔', skipped: '↷', error: '✖', 'n/a': '—' };
		return labels[ state ] || state;
	}

	function renderQueue( data ) {
		if ( queueUsage ) {
			queueUsage.textContent = 'Hôm nay đã tạo ' + Number( data.used_today || 0 ) + '/' + Number( data.daily_limit || 0 ) + ' ảnh AI (giới hạn cấu hình trong trang Ảnh AI & Canva).';
		}
		const job = data.job;
		const running = job && job.status === 'running';
		const paused = job && job.status === 'paused';
		if ( queueStart ) { queueStart.hidden = !! ( running || paused ); }
		if ( queuePause ) { queuePause.hidden = ! running; }
		if ( queueResume ) { queueResume.hidden = ! paused; }
		if ( queueCancel ) { queueCancel.hidden = ! ( running || paused ); }
		if ( ! job ) {
			if ( queueProgress ) { queueProgress.hidden = true; }
			return;
		}
		queueProgress.hidden = false;
		const percent = job.total_posts ? Math.round( ( job.done_posts / job.total_posts ) * 100 ) : 0;
		queueBarFill.style.width = percent + '%';
		const statusLabels = { running: 'Đang chạy', paused: 'Tạm dừng', completed: 'Hoàn thành', cancelled: 'Đã hủy' };
		let note = '';
		if ( paused && job.pause_reason === 'daily_limit' ) {
			note = ' — đã chạm giới hạn ảnh/ngày, hãy tiếp tục vào ngày mai hoặc tăng giới hạn';
		}
		queueSummary.textContent = ( statusLabels[ job.status ] || job.status ) + ': ' + job.done_posts + '/' + job.total_posts + ' bài · ' + job.images + ' ảnh đã tạo · ' + job.errors + ' lỗi' + note;
		queueItems.innerHTML = ( job.items || [] ).map( function ( item ) {
			const error = item.error ? ' <em>' + escapeHtml( item.error ) + '</em>' : '';
			let insertInfo = '';
			if ( item.insert && item.insert !== 'n/a' ) {
				insertInfo = ' · chèn vào bài ' + taskLabel( item.insert );
				if ( item.insert === 'done' ) {
					insertInfo += ' (' + Number( item.inserted || 0 ) + ' ảnh)';
				}
			}
			return '<li><code>#' + Number( item.post_id ) + '</code> ' + escapeHtml( item.title ) + ' — đại diện ' + taskLabel( item.featured ) + ' · trong bài ' + taskLabel( item.content ) + insertInfo + ' · ' + Number( item.images ) + ' ảnh' + error + '</li>';
		} ).join( '' );
	}

	async function queueLoop() {
		if ( queueLoopActive ) {
			return;
		}
		queueLoopActive = true;
		try {
			for ( ;; ) {
				let data;
				try {
					data = await request( '/queue/step', 'POST', {} );
				} catch ( error ) {
					if ( error && error.code === 'ntci_queue_busy' ) {
						await new Promise( function ( resolve ) { setTimeout( resolve, 3000 ); } );
						continue;
					}
					setFeedback( error.message || config.labels.networkError, 'error' );
					break;
				}
				renderQueue( data );
				if ( ! data.job || data.job.status !== 'running' ) {
					if ( data.job && data.job.status === 'completed' ) {
						let doneMessage = 'Đợt chạy hàng loạt đã hoàn thành: ' + data.job.images + ' ảnh.';
						if ( data.job.auto_insert ) {
							const insertedPosts = ( data.job.items || [] ).filter( function ( item ) { return item.insert === 'done'; } ).length;
							doneMessage += ' Đã tự chèn ảnh vào ' + insertedPosts + ' bài theo kế hoạch (mỗi bài có thể hoàn tác riêng).';
						} else {
							doneMessage += ' Hãy duyệt trong từng bài hoặc trang Ảnh AI & Canva.';
						}
						setFeedback( doneMessage, 'success' );
						loadCandidates();
					}
					break;
				}
			}
		} finally {
			queueLoopActive = false;
		}
	}

	async function refreshQueueStatus() {
		try {
			const data = await request( '/queue/status' );
			renderQueue( data );
			if ( data.job && data.job.status === 'running' ) {
				setFeedback( 'Đang có đợt chạy hàng loạt dở dang — tiếp tục xử lý…' );
				queueLoop();
			}
		} catch ( error ) {
			// Trang vẫn dùng được khi REST queue lỗi; chỉ ghi feedback.
		}
	}

	if ( queueStart ) {
		queueStart.addEventListener( 'click', async function () {
			const autoInsert = !! ( document.querySelector( '#ntci-queue-autoinsert' ) || {} ).checked;
			if ( ! window.confirm( autoInsert ? config.labels.confirmQueueAutoInsert : config.labels.confirmQueue ) ) {
				return;
			}
			try {
				const data = await request( '/queue/start', 'POST', {
					include_featured: document.querySelector( '#ntci-queue-featured' ).checked,
					include_content: document.querySelector( '#ntci-queue-content' ).checked,
					auto_insert: autoInsert,
					limit: Number( document.querySelector( '#ntci-queue-limit' ).value || 10 )
				} );
				renderQueue( data );
				setFeedback( 'Đã tạo đợt chạy ' + data.job.total_posts + ' bài. Đang xử lý từng ảnh…', 'success' );
				queueLoop();
			} catch ( error ) {
				setFeedback( error.message || config.labels.networkError, 'error' );
			}
		} );
	}
	if ( queuePause ) {
		queuePause.addEventListener( 'click', async function () {
			try { renderQueue( await request( '/queue/pause', 'POST', {} ) ); } catch ( error ) { setFeedback( error.message, 'error' ); }
		} );
	}
	if ( queueResume ) {
		queueResume.addEventListener( 'click', async function () {
			try {
				renderQueue( await request( '/queue/resume', 'POST', {} ) );
				queueLoop();
			} catch ( error ) { setFeedback( error.message, 'error' ); }
		} );
	}
	if ( queueCancel ) {
		queueCancel.addEventListener( 'click', async function () {
			if ( ! window.confirm( config.labels.confirmQueueCancel ) ) {
				return;
			}
			try { renderQueue( await request( '/queue/cancel', 'POST', {} ) ); } catch ( error ) { setFeedback( error.message, 'error' ); }
		} );
	}

	const cleanupButton = document.querySelector( '#ntci-cleanup-run' );
	const cleanupResult = document.querySelector( '#ntci-cleanup-result' );
	if ( cleanupButton ) {
		cleanupButton.addEventListener( 'click', async function () {
			if ( ! window.confirm( config.labels.confirmCleanup ) ) {
				return;
			}
			cleanupButton.disabled = true;
			cleanupButton.textContent = 'Đang dọn…';
			try {
				const data = await request( '/content-images/cleanup', 'POST', {} );
				const freedMb = ( Number( data.freed_bytes || 0 ) / 1048576 ).toFixed( 1 );
				const message = 'Đã xoá ' + Number( data.deleted_attachments || 0 ) + ' ảnh (' + freedMb + ' MB), dọn ' + Number( data.deleted_records || 0 ) + ' bản ghi; giữ lại ' + Number( data.kept || 0 ) + ' ảnh đang dùng.';
				if ( cleanupResult ) {
					cleanupResult.textContent = message;
				}
				setFeedback( message, 'success' );
				loadCandidates();
				if ( currentPostId ) {
					openPlan( currentPostId );
				}
			} catch ( error ) {
				setFeedback( error.message || config.labels.networkError, 'error' );
			}
			cleanupButton.disabled = false;
			cleanupButton.textContent = 'Dọn ảnh không dùng';
		} );
	}

	loadCandidates();
	refreshQueueStatus();
}() );
