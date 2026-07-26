( function () {
	'use strict';

	const config = window.NTContentImagesGeneration;
	const apiFetch = window.wp && window.wp.apiFetch;
	if ( ! config || ! apiFetch ) {
		return;
	}
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );

	const candidatesBody = document.querySelector( '#ntci-generation-candidates' );
	const gallery = document.querySelector( '#ntci-generation-gallery' );
	const feedback = document.querySelector( '#ntci-generation-feedback' );
	const refresh = document.querySelector( '#ntci-generation-refresh' );
	const providerSelect = document.querySelector( '#ntci-provider' );
	const openRouterList = document.querySelector( '#ntci-openrouter-models' );
	let busy = false;

	function request( path, method, data ) {
		return apiFetch( { path: config.root + path, method: method || 'GET', data: data } );
	}

	function escapeHtml( value ) {
		return String( value || '' ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	}

	function setFeedback( message, type ) {
		feedback.textContent = message || '';
		feedback.className = 'ntci-generation-feedback' + ( type ? ' ntci-generation-feedback--' + type : '' );
	}

	async function loadOpenRouterModels() {
		if ( ! openRouterList ) {
			return;
		}
		try {
			const data = await request( '/generations/providers/openrouter/models' );
			openRouterList.innerHTML = ( data.items || [] ).map( function ( item ) {
				return '<option value="' + escapeHtml( item.id ) + '">' + escapeHtml( item.name ) + '</option>';
			} ).join( '' );
		} catch ( error ) {
			openRouterList.innerHTML = '';
		}
	}

	async function loadCandidates() {
		candidatesBody.innerHTML = '<tr><td colspan="5">Đang tải…</td></tr>';
		try {
			const data = await request( '/generations/candidates?limit=30' );
			if ( ! data.items || ! data.items.length ) {
				candidatesBody.innerHTML = '<tr><td colspan="5">Không còn nội dung đã audit bị thiếu ảnh đại diện.</td></tr>';
				return;
			}
			candidatesBody.innerHTML = data.items.map( function ( item ) {
				return '<tr><td><strong>' + escapeHtml( item.title ) + '</strong><br><code>#' + Number( item.post_id ) + '</code></td><td><code>' + escapeHtml( item.post_type ) + '</code></td><td>' + Number( item.word_count || 0 ).toLocaleString() + '</td><td><strong>' + Number( item.priority_score || 0 ) + '</strong> ' + escapeHtml( item.priority_label ) + '</td><td><button type="button" class="button button-primary ntci-generate" data-post-id="' + Number( item.post_id ) + '">Tạo ảnh</button></td></tr>';
			} ).join( '' );
		} catch ( error ) {
			candidatesBody.innerHTML = '<tr><td colspan="5">Không thể tải danh sách.</td></tr>';
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	function statusLabel( status ) {
		const labels = { generated: 'Chờ duyệt', approved: 'Đã dùng', rejected: 'Đã từ chối', failed: 'Lỗi' };
		return labels[ status ] || status;
	}

	function canvaActions( item ) {
		if ( ! config.canvaConnected || ! item.attachment_id || item.status === 'failed' ) {
			return '';
		}
		const canva = item.response && item.response.canva ? item.response.canva : {};
		if ( canva.design_id ) {
			const edit = canva.edit_url ? '<a class="button" href="' + escapeHtml( canva.edit_url ) + '" target="_blank" rel="noopener noreferrer">Mở trong Canva</a>' : '';
			return '<div class="ntci-generation-actions ntci-generation-canva-actions">' + edit + '<button type="button" class="button ntci-canva-import" data-id="' + Number( item.id ) + '">Nhập bản Canva</button></div>';
		}
		return '<div class="ntci-generation-actions ntci-generation-canva-actions"><button type="button" class="button ntci-canva-create" data-id="' + Number( item.id ) + '">Gửi sang Canva</button></div>';
	}

	async function loadGenerations() {
		gallery.innerHTML = '<p>Đang tải…</p>';
		try {
			const data = await request( '/generations?per_page=30' );
			if ( ! data.items || ! data.items.length ) {
				gallery.innerHTML = '<p>Chưa có ảnh AI nào.</p>';
				return;
			}
			gallery.innerHTML = data.items.map( function ( item ) {
				const image = item.image_url ? '<img src="' + escapeHtml( item.image_url ) + '" alt="">' : '<div class="ntci-generation-placeholder">Không có ảnh</div>';
				const actions = item.status === 'generated' ? '<div class="ntci-generation-actions"><button type="button" class="button button-primary ntci-approve" data-id="' + Number( item.id ) + '">Duyệt làm ảnh đại diện</button><button type="button" class="button ntci-reject" data-id="' + Number( item.id ) + '">Từ chối</button></div>' : '';
				const error = item.error_message ? '<p class="ntci-generation-error">' + escapeHtml( item.error_message ) + '</p>' : '';
				return '<article class="ntci-generation-card">' + image + '<div class="ntci-generation-card-body"><h3>' + escapeHtml( item.title ) + '</h3><p><span class="ntci-generation-badge ntci-generation-badge--' + escapeHtml( item.status ) + '">' + escapeHtml( statusLabel( item.status ) ) + '</span> <code>#' + Number( item.id ) + '</code></p><p><strong>Provider:</strong> ' + escapeHtml( item.provider ) + '<br><strong>Model:</strong> ' + escapeHtml( item.model ) + '</p><details><summary>Prompt</summary><pre>' + escapeHtml( item.prompt ) + '</pre></details>' + error + actions + canvaActions( item ) + '</div></article>';
			} ).join( '' );
		} catch ( error ) {
			gallery.innerHTML = '<p>Không thể tải danh sách ảnh.</p>';
		}
	}

	async function generate( button ) {
		if ( busy || ! window.confirm( config.labels.confirmGenerate ) ) {
			return;
		}
		busy = true;
		button.disabled = true;
		button.textContent = 'Đang phân tích và tạo ảnh…';
		setFeedback( 'Đang gửi yêu cầu tạo ảnh. Quá trình có thể mất vài phút.' );
		try {
			await request( '/generations/featured', 'POST', { post_id: Number( button.dataset.postId ) } );
			setFeedback( 'Đã tạo ảnh và lưu vào Media Library. Hãy xem trước rồi duyệt.', 'success' );
			await Promise.all( [ loadCandidates(), loadGenerations() ] );
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		} finally {
			busy = false;
			button.disabled = false;
			button.textContent = 'Tạo ảnh';
		}
	}

	async function updateStatus( id, action ) {
		if ( busy || ( action === 'approve' && ! window.confirm( config.labels.confirmApprove ) ) ) {
			return;
		}
		busy = true;
		try {
			await request( '/generations/' + Number( id ) + '/' + action, 'POST', {} );
			setFeedback( action === 'approve' ? 'Đã đặt ảnh đại diện thành công.' : 'Đã từ chối ảnh.', 'success' );
			await Promise.all( [ loadCandidates(), loadGenerations() ] );
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		} finally {
			busy = false;
		}
	}

	async function canvaAction( id, action ) {
		const text = action === 'design' ? config.labels.confirmCanva : config.labels.confirmImport;
		if ( busy || ! window.confirm( text ) ) {
			return;
		}
		busy = true;
		setFeedback( action === 'design' ? 'Đang tạo thiết kế Canva…' : 'Đang nhập bản Canva…' );
		try {
			await request( '/generations/' + Number( id ) + '/canva/' + action, 'POST', {} );
			setFeedback( action === 'design' ? 'Đã tạo thiết kế Canva.' : 'Đã nhập bản Canva thành ảnh mới chờ duyệt.', 'success' );
			await loadGenerations();
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		} finally {
			busy = false;
		}
	}

	document.addEventListener( 'click', function ( event ) {
		const generateButton = event.target.closest( '.ntci-generate' );
		const approveButton = event.target.closest( '.ntci-approve' );
		const rejectButton = event.target.closest( '.ntci-reject' );
		const canvaCreate = event.target.closest( '.ntci-canva-create' );
		const canvaImport = event.target.closest( '.ntci-canva-import' );
		if ( generateButton ) {
			generate( generateButton );
		} else if ( approveButton ) {
			updateStatus( approveButton.dataset.id, 'approve' );
		} else if ( rejectButton ) {
			updateStatus( rejectButton.dataset.id, 'reject' );
		} else if ( canvaCreate ) {
			canvaAction( canvaCreate.dataset.id, 'design' );
		} else if ( canvaImport ) {
			canvaAction( canvaImport.dataset.id, 'import' );
		}
	} );

	if ( refresh ) {
		refresh.addEventListener( 'click', function () { Promise.all( [ loadCandidates(), loadGenerations() ] ); } );
	}
	if ( providerSelect ) {
		if ( providerSelect.value === 'openrouter' ) {
			loadOpenRouterModels();
		}
		providerSelect.addEventListener( 'change', function () {
			if ( providerSelect.value === 'openrouter' ) {
				loadOpenRouterModels();
			}
		} );
	}
	Promise.all( [ loadCandidates(), loadGenerations() ] );
}() );
