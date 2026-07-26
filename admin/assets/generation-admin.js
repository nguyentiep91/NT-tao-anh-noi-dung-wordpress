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
	let busy = false;

	function request( path, method = 'GET', data = undefined ) {
		return apiFetch( { path: config.root + path, method, data } );
	}

	function escapeHtml( value ) {
		return String( value ?? '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function setFeedback( message, type = '' ) {
		feedback.textContent = message || '';
		feedback.className = 'ntci-generation-feedback' + ( type ? ' ntci-generation-feedback--' + type : '' );
	}

	async function loadCandidates() {
		candidatesBody.innerHTML = '<tr><td colspan="5">Đang tải…</td></tr>';
		try {
			const data = await request( '/generations/candidates?limit=30' );
			if ( ! data.items || ! data.items.length ) {
				candidatesBody.innerHTML = '<tr><td colspan="5">Không còn bài đã audit bị thiếu ảnh đại diện.</td></tr>';
				return;
			}
			candidatesBody.innerHTML = data.items.map( ( item ) => '<tr>' +
				'<td><strong>' + escapeHtml( item.title ) + '</strong><br><code>#' + Number( item.post_id ) + '</code></td>' +
				'<td><code>' + escapeHtml( item.post_type ) + '</code></td>' +
				'<td>' + Number( item.word_count || 0 ).toLocaleString() + '</td>' +
				'<td><strong>' + Number( item.priority_score || 0 ) + '</strong> ' + escapeHtml( item.priority_label ) + '</td>' +
				'<td><button type="button" class="button button-primary ntci-generate" data-post-id="' + Number( item.post_id ) + '">Tạo ảnh</button></td>' +
			'</tr>' ).join( '' );
		} catch ( error ) {
			candidatesBody.innerHTML = '<tr><td colspan="5">Không thể tải danh sách.</td></tr>';
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	function statusLabel( status ) {
		return { generated: 'Chờ duyệt', approved: 'Đã dùng', rejected: 'Đã từ chối', failed: 'Lỗi' }[ status ] || status;
	}

	async function loadGenerations() {
		gallery.innerHTML = '<p>Đang tải…</p>';
		try {
			const data = await request( '/generations?per_page=30' );
			if ( ! data.items || ! data.items.length ) {
				gallery.innerHTML = '<p>Chưa có ảnh AI nào.</p>';
				return;
			}
			gallery.innerHTML = data.items.map( ( item ) => {
				const image = item.image_url ? '<img src="' + escapeHtml( item.image_url ) + '" alt="">' : '<div class="ntci-generation-placeholder">Không có ảnh</div>';
				const actions = item.status === 'generated' ? '<div class="ntci-generation-actions"><button type="button" class="button button-primary ntci-approve" data-id="' + Number( item.id ) + '">Duyệt làm ảnh đại diện</button><button type="button" class="button ntci-reject" data-id="' + Number( item.id ) + '">Từ chối</button></div>' : '';
				const error = item.error_message ? '<p class="ntci-generation-error">' + escapeHtml( item.error_message ) + '</p>' : '';
				return '<article class="ntci-generation-card">' + image + '<div class="ntci-generation-card-body"><h3>' + escapeHtml( item.title ) + '</h3><p><span class="ntci-generation-badge ntci-generation-badge--' + escapeHtml( item.status ) + '">' + escapeHtml( statusLabel( item.status ) ) + '</span> <code>#' + Number( item.id ) + '</code></p><p><strong>Model:</strong> ' + escapeHtml( item.model ) + '</p><details><summary>Prompt</summary><pre>' + escapeHtml( item.prompt ) + '</pre></details>' + error + actions + '</div></article>';
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
		setFeedback( 'Đang gửi yêu cầu tạo ảnh. Vui lòng giữ nguyên trang này; quá trình có thể mất vài phút.' );
		try {
			const item = await request( '/generations/featured', 'POST', { post_id: Number( button.dataset.postId ) } );
			setFeedback( 'Đã tạo ảnh và lưu vào Media Library. Hãy xem trước rồi duyệt.', 'success' );
			await Promise.all( [ loadCandidates(), loadGenerations() ] );
			return item;
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		} finally {
			busy = false;
			button.disabled = false;
			button.textContent = 'Tạo ảnh';
		}
	}

	async function updateStatus( id, action ) {
		if ( busy ) {
			return;
		}
		if ( action === 'approve' && ! window.confirm( config.labels.confirmApprove ) ) {
			return;
		}
		busy = true;
		setFeedback( action === 'approve' ? 'Đang đặt ảnh đại diện…' : 'Đang từ chối ảnh…' );
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

	document.addEventListener( 'click', ( event ) => {
		const generateButton = event.target.closest( '.ntci-generate' );
		const approveButton = event.target.closest( '.ntci-approve' );
		const rejectButton = event.target.closest( '.ntci-reject' );
		if ( generateButton ) {
			generate( generateButton );
		} else if ( approveButton ) {
			updateStatus( approveButton.dataset.id, 'approve' );
		} else if ( rejectButton ) {
			updateStatus( rejectButton.dataset.id, 'reject' );
		}
	} );

	refresh.addEventListener( 'click', () => Promise.all( [ loadCandidates(), loadGenerations() ] ) );
	Promise.all( [ loadCandidates(), loadGenerations() ] );
}() );
