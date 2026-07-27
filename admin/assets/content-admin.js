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

	function renderSlots( plan ) {
		slotsWrap.innerHTML = plan.slots.map( function ( slot ) {
			const record = slot.record;
			let body = '';
			if ( record ) {
				const image = record.image_url ? '<img src="' + escapeHtml( record.image_url ) + '" alt="">' : '<div class="ntci-content-placeholder">Chưa có ảnh</div>';
				let actions = '';
				if ( record.status === 'generated' ) {
					actions = '<button type="button" class="button button-primary ntci-content-approve" data-id="' + Number( record.id ) + '">Duyệt</button> <button type="button" class="button ntci-content-reject" data-id="' + Number( record.id ) + '">Từ chối</button>';
				} else if ( record.status === 'approved' ) {
					actions = '<button type="button" class="button ntci-content-reject" data-id="' + Number( record.id ) + '">Bỏ duyệt</button>';
				}
				body = image + '<p><span class="ntci-content-badge ntci-content-badge--' + escapeHtml( record.status ) + '">' + escapeHtml( statusLabel( record.status ) ) + '</span></p><p class="ntci-content-slot-actions">' + actions + '</p>';
			} else {
				body = '<div class="ntci-content-placeholder">' + ( slot.safe ? 'Chưa tạo ảnh cho vị trí này' : 'Vị trí cần xử lý thủ công — plugin sẽ không tự chèn' ) + '</div><p><button type="button" class="button ntci-content-generate-one" data-index="' + Number( slot.index ) + '" ' + ( slot.safe ? '' : 'disabled' ) + '>Tạo ảnh vị trí này</button></p>';
			}
			return '<article class="ntci-content-slot"><header><strong>Ảnh ' + Number( slot.index ) + '</strong> — ' + escapeHtml( placementLabel( slot.placement ) ) + '<br><small>' + escapeHtml( slot.purpose || '' ) + '</small></header>' + body + '</article>';
		} ).join( '' );
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
	loadCandidates();
}() );
