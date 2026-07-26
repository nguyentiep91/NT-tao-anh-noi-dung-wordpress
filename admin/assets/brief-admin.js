( function () {
	'use strict';

	const config = window.NTContentImagesBriefs;
	const apiFetch = window.wp && window.wp.apiFetch;

	if ( ! config || ! apiFetch ) {
		return;
	}

	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );

	const state = {
		page: 1,
		perPage: 20,
		currentDetailId: 0,
	};
	const $ = ( selector ) => document.querySelector( selector );
	const $$ = ( selector ) => Array.from( document.querySelectorAll( selector ) );
	const feedback = $( '#ntci-brief-feedback' );

	function request( path, method = 'GET', data = undefined ) {
		return apiFetch( {
			path: config.root + path,
			method,
			data,
		} );
	}

	function escapeHtml( value ) {
		return String( value ?? '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function setFeedback( message, type = 'info' ) {
		feedback.textContent = message || '';
		feedback.className = 'ntci-brief-feedback ntci-brief-feedback--' + type;
	}

	function formatStatus( status ) {
		const map = {
			draft: 'Nháp',
			pending_review: 'Chờ duyệt',
			approved: 'Đã duyệt',
			rejected: 'Từ chối',
			outdated: 'Lỗi thời',
			valid: 'Hợp lệ',
			warning: 'Cảnh báo',
			invalid: 'Không hợp lệ',
		};

		return map[ status ] || status || '—';
	}

	function buildQuery( params ) {
		const query = new URLSearchParams();

		Object.entries( params ).forEach( ( [ key, value ] ) => {
			if ( value !== '' && value !== undefined && value !== null ) {
				query.set( key, value );
			}
		} );

		return query.toString();
	}

	async function loadCandidates() {
		const tbody = $( '#ntci-brief-candidates-table tbody' );
		tbody.innerHTML = '<tr><td colspan="5">Đang tải…</td></tr>';

		try {
			const data = await request( '/briefs/candidates?limit=20' );

			if ( ! data.items || ! data.items.length ) {
				tbody.innerHTML = '<tr><td colspan="5">Không có bài ưu tiên phù hợp.</td></tr>';
				return;
			}

			tbody.innerHTML = data.items.map( ( item ) => {
				const disabled = item.has_brief ? 'disabled' : '';
				const status = item.has_brief ? '<button type="button" class="button-link ntci-brief-open" data-id="' + Number( item.brief_id ) + '">Đã có brief</button>' : 'Chưa có';
				return '<tr>' +
					'<th class="check-column"><input type="checkbox" class="ntci-brief-candidate" value="' + Number( item.post_id ) + '" ' + disabled + '></th>' +
					'<td><strong>' + escapeHtml( item.title ) + '</strong><br><code>#' + Number( item.post_id ) + '</code></td>' +
					'<td>' + Number( item.word_count || 0 ).toLocaleString() + '</td>' +
					'<td><span class="ntci-brief-badge ntci-brief-badge--priority">' + Number( item.priority_score || 0 ) + '</span></td>' +
					'<td>' + status + '</td>' +
				'</tr>';
			} ).join( '' );
		} catch ( error ) {
			tbody.innerHTML = '<tr><td colspan="5">Không thể tải danh sách.</td></tr>';
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	async function generateSelected() {
		const postIds = $$( '.ntci-brief-candidate:checked' ).map( ( input ) => Number( input.value ) );

		if ( ! postIds.length ) {
			setFeedback( 'Hãy chọn ít nhất một bài viết.', 'warning' );
			return;
		}

		const button = $( '#ntci-brief-generate-selected' );
		button.disabled = true;
		setFeedback( 'Đang tạo kế hoạch hình ảnh…' );

		try {
			const result = await request( '/briefs/generate', 'POST', { post_ids: postIds } );
			setFeedback( 'Đã tạo ' + Number( result.generated || 0 ) + ' brief; lỗi ' + Number( result.failed || 0 ) + '.', result.failed ? 'warning' : 'success' );
			await Promise.all( [ loadCandidates(), loadSummary(), loadBriefs() ] );
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		} finally {
			button.disabled = false;
		}
	}

	async function loadSummary() {
		const container = $( '#ntci-brief-summary' );

		try {
			const data = await request( '/briefs/summary' );
			const cards = [
				[ 'total', 'Tổng brief' ],
				[ 'drafts', 'Nháp' ],
				[ 'pending', 'Chờ duyệt' ],
				[ 'approved', 'Đã duyệt' ],
				[ 'outdated', 'Lỗi thời' ],
				[ 'invalid', 'Không hợp lệ' ],
			];
			container.innerHTML = cards.map( ( card ) => '<div class="ntci-brief-card"><strong>' + Number( data[ card[ 0 ] ] || 0 ).toLocaleString() + '</strong><span>' + escapeHtml( card[ 1 ] ) + '</span></div>' ).join( '' );
		} catch ( error ) {
			container.innerHTML = '<p>Không thể tải thống kê.</p>';
		}
	}

	function getFilters() {
		return {
			page: state.page,
			per_page: state.perPage,
			search: $( '#ntci-brief-search' ).value.trim(),
			status: $( '#ntci-brief-status-filter' ).value,
			content_type: $( '#ntci-brief-type-filter' ).value,
		};
	}

	async function loadBriefs() {
		const tbody = $( '#ntci-brief-list-table tbody' );
		tbody.innerHTML = '<tr><td colspan="7">Đang tải…</td></tr>';

		try {
			const data = await request( '/briefs?' + buildQuery( getFilters() ) );

			if ( ! data.items || ! data.items.length ) {
				tbody.innerHTML = '<tr><td colspan="7">' + escapeHtml( config.labels.empty ) + '</td></tr>';
				renderPagination( 0 );
				return;
			}

			tbody.innerHTML = data.items.map( ( item ) => {
				const featured = Number( item.featured_image_required ) ? 'Cần tạo' : 'Đã có';
				return '<tr>' +
					'<td><button type="button" class="button-link ntci-brief-open" data-id="' + Number( item.id ) + '"><strong>' + escapeHtml( item.title ) + '</strong></button><br><code>#' + Number( item.post_id ) + '</code></td>' +
					'<td>' + escapeHtml( item.topic ) + '</td>' +
					'<td><code>' + escapeHtml( item.content_type ) + '</code></td>' +
					'<td>' + escapeHtml( featured ) + '</td>' +
					'<td>' + Number( item.recommended_content_images || 0 ) + '</td>' +
					'<td><span class="ntci-brief-badge ntci-brief-badge--' + escapeHtml( item.validation_status ) + '">' + escapeHtml( formatStatus( item.validation_status ) ) + '</span></td>' +
					'<td><span class="ntci-brief-badge">' + escapeHtml( formatStatus( item.status ) ) + '</span></td>' +
				'</tr>';
			} ).join( '' );
			renderPagination( Number( data.total || 0 ) );
		} catch ( error ) {
			tbody.innerHTML = '<tr><td colspan="7">Không thể tải danh sách brief.</td></tr>';
		}
	}

	function renderPagination( total ) {
		const container = $( '#ntci-brief-pagination' );
		const pages = Math.ceil( total / state.perPage );

		if ( pages <= 1 ) {
			container.innerHTML = '';
			return;
		}

		container.innerHTML = '<button type="button" class="button" id="ntci-brief-prev" ' + ( state.page <= 1 ? 'disabled' : '' ) + '>Trước</button>' +
			'<span>Trang ' + state.page + ' / ' + pages + '</span>' +
			'<button type="button" class="button" id="ntci-brief-next" ' + ( state.page >= pages ? 'disabled' : '' ) + '>Sau</button>';
	}

	function renderList( values ) {
		if ( ! values || ! values.length ) {
			return '<em>Không có</em>';
		}

		return '<ul>' + values.map( ( value ) => '<li><code>' + escapeHtml( value ) + '</code></li>' ).join( '' ) + '</ul>';
	}

	async function openDetail( id ) {
		const panel = $( '#ntci-brief-detail-panel' );
		const content = $( '#ntci-brief-detail' );
		state.currentDetailId = Number( id );
		panel.hidden = false;
		content.innerHTML = '<p>Đang tải…</p>';
		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );

		try {
			const item = await request( '/briefs/' + Number( id ) );
			const brief = item.brief || {};
			const featured = brief.featured_image || {};
			const images = Array.isArray( brief.content_images ) ? brief.content_images : [];
			const validation = brief.validation || {};
			content.innerHTML = '<div class="ntci-brief-detail-grid">' +
				'<div><strong>Bài viết</strong><span>' + escapeHtml( item.title ) + ' (#' + Number( item.post_id ) + ')</span></div>' +
				'<div><strong>Chủ đề</strong><span>' + escapeHtml( brief.topic ) + '</span></div>' +
				'<div><strong>Loại nội dung</strong><span><code>' + escapeHtml( brief.content_type ) + '</code></span></div>' +
				'<div><strong>Search intent</strong><span><code>' + escapeHtml( brief.search_intent ) + '</code></span></div>' +
				'<div><strong>Visual strategy</strong><span><code>' + escapeHtml( brief.visual_strategy ) + '</code></span></div>' +
				'<div><strong>Ảnh đại diện</strong><span>' + ( featured.required ? 'Cần tạo' : 'Đã có' ) + '</span></div>' +
				'<div><strong>Ảnh nội dung cần tạo</strong><span>' + Number( brief.recommended_content_images || 0 ) + '</span></div>' +
				'<div><strong>Kiểm tra</strong><span>' + escapeHtml( formatStatus( item.validation_status ) ) + '</span></div>' +
			'</div>' +
			'<h3>Chiến lược hình ảnh</h3><p>' + escapeHtml( brief.visual_direction && brief.visual_direction.art_direction ) + '</p>' +
			'<h3>Kế hoạch ảnh nội dung</h3>' + ( images.length ? '<ol class="ntci-brief-image-plan">' + images.map( ( image ) => '<li><strong>' + escapeHtml( image.purpose ) + '</strong><br><code>' + escapeHtml( image.visual_type ) + '</code><br><span>Vị trí: ' + escapeHtml( image.placement && image.placement.type ) + ( image.placement && image.placement.heading_text ? ' — ' + escapeHtml( image.placement.heading_text ) : '' ) + '</span></li>' ).join( '' ) + '</ol>' : '<p>Không cần thêm ảnh nội dung.</p>' ) +
			'<h3>Hạn chế an toàn</h3>' + renderList( brief.restrictions ) +
			'<h3>Cảnh báo xác thực</h3>' + renderList( validation.warnings ) +
			'<h3>Lỗi xác thực</h3>' + renderList( validation.errors );
			renderDetailActions( item );
		} catch ( error ) {
			content.innerHTML = '<p>Không thể tải chi tiết brief.</p>';
		}
	}

	function renderDetailActions( item ) {
		const container = $( '#ntci-brief-detail-actions' );
		const buttons = [];

		if ( item.status === 'draft' ) {
			buttons.push( '<button type="button" class="button ntci-brief-status" data-status="pending_review">Gửi duyệt</button>' );
		}

		if ( item.status === 'draft' || item.status === 'pending_review' ) {
			buttons.push( '<button type="button" class="button button-primary ntci-brief-status" data-status="approved">Duyệt</button>' );
			buttons.push( '<button type="button" class="button button-link-delete ntci-brief-status" data-status="rejected">Từ chối</button>' );
		}

		container.innerHTML = buttons.join( '' );
	}

	async function updateStatus( status ) {
		if ( status === 'approved' && ! window.confirm( config.labels.confirmApprove ) ) {
			return;
		}

		if ( status === 'rejected' && ! window.confirm( config.labels.confirmReject ) ) {
			return;
		}

		try {
			await request( '/briefs/' + state.currentDetailId + '/status', 'POST', { status } );
			setFeedback( 'Đã cập nhật trạng thái brief.', 'success' );
			await Promise.all( [ openDetail( state.currentDetailId ), loadBriefs(), loadSummary() ] );
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	document.addEventListener( 'click', ( event ) => {
		const openButton = event.target.closest( '.ntci-brief-open' );
		const statusButton = event.target.closest( '.ntci-brief-status' );

		if ( openButton ) {
			openDetail( openButton.dataset.id );
		}

		if ( statusButton ) {
			updateStatus( statusButton.dataset.status );
		}

		if ( event.target.id === 'ntci-brief-prev' ) {
			state.page = Math.max( 1, state.page - 1 );
			loadBriefs();
		}

		if ( event.target.id === 'ntci-brief-next' ) {
			state.page += 1;
			loadBriefs();
		}
	} );

	$( '#ntci-brief-generate-selected' ).addEventListener( 'click', generateSelected );
	$( '#ntci-brief-refresh-candidates' ).addEventListener( 'click', loadCandidates );
	$( '#ntci-brief-refresh-list' ).addEventListener( 'click', loadBriefs );
	$( '#ntci-brief-apply-filters' ).addEventListener( 'click', () => {
		state.page = 1;
		loadBriefs();
	} );
	$( '#ntci-brief-select-all' ).addEventListener( 'change', ( event ) => {
		$$( '.ntci-brief-candidate:not(:disabled)' ).forEach( ( input ) => {
			input.checked = event.target.checked;
		} );
	} );
	$( '#ntci-brief-detail-close' ).addEventListener( 'click', () => {
		$( '#ntci-brief-detail-panel' ).hidden = true;
	} );

	Promise.all( [ loadCandidates(), loadSummary(), loadBriefs() ] );
}() );
