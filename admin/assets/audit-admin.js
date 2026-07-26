( function () {
	'use strict';

	const config = window.NTContentImagesAudit;
	const apiFetch = window.wp && window.wp.apiFetch;

	if ( ! config || ! apiFetch ) {
		return;
	}

	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );

	const state = {
		page: 1,
		perPage: 20,
		processing: false,
		job: null,
	};

	const $ = ( selector ) => document.querySelector( selector );
	const $$ = ( selector ) => Array.from( document.querySelectorAll( selector ) );
	const feedback = $( '#ntci-feedback' );

	function request( path, method = 'GET', data = undefined ) {
		return apiFetch( {
			path: config.root + path,
			method,
			data,
		} );
	}

	function setFeedback( message, type = 'info' ) {
		feedback.textContent = message || '';
		feedback.className = 'ntci-feedback ntci-feedback--' + type;
	}

	function getCheckedValues( name ) {
		return $$( 'input[name="' + name + '"]:checked' ).map( ( input ) => input.value );
	}

	function getJobConfig() {
		return {
			post_types: getCheckedValues( 'post_types' ),
			post_statuses: getCheckedValues( 'post_statuses' ),
			batch_size: Number( $( '#ntci-batch-size' ).value || 20 ),
			scan_mode: $( '#ntci-scan-mode' ).value || 'changed',
		};
	}

	function getFilters() {
		return {
			search: $( '#ntci-search' ).value.trim(),
			featured: $( '#ntci-filter-featured' ).value,
			content_images: $( '#ntci-filter-content' ).value,
			priority_label: $( '#ntci-filter-priority' ).value,
			page: state.page,
			per_page: state.perPage,
		};
	}

	function toQuery( values ) {
		const params = new URLSearchParams();
		Object.entries( values ).forEach( ( [ key, value ] ) => {
			if ( value !== '' && value !== null && value !== undefined ) {
				params.set( key, String( value ) );
			}
		} );
		return params.toString();
	}

	function updateControls( job ) {
		const status = job && job.status ? job.status : 'idle';
		$( '#ntci-start' ).disabled = status === 'running' || status === 'paused';
		$( '#ntci-pause' ).disabled = status !== 'running';
		$( '#ntci-resume' ).disabled = status !== 'paused';
		$( '#ntci-cancel' ).disabled = status !== 'running' && status !== 'paused';
	}

	function renderProgress( job ) {
		state.job = job;
		const progress = Math.max( 0, Math.min( 100, Number( job.progress || 0 ) ) );
		const bar = $( '#ntci-progress-bar' );
		const container = bar.parentElement;
		bar.style.width = progress + '%';
		container.setAttribute( 'aria-valuenow', String( progress ) );
		$( '#ntci-progress-meta' ).textContent = [
			'Trạng thái: ' + ( job.status || 'idle' ),
			'Đã xử lý: ' + Number( job.processed_posts || 0 ) + ' / ' + Number( job.total_posts || 0 ),
			'Đã quét: ' + Number( job.scanned_posts || 0 ),
			'Bỏ qua: ' + Number( job.skipped_posts || 0 ),
			'Lỗi: ' + Number( job.failed_posts || 0 ),
			'Tiến độ: ' + progress.toFixed( 2 ) + '%',
		].join( ' · ' );
		updateControls( job );
	}

	async function refreshStatus() {
		try {
			const job = await request( '/audit/status' );
			renderProgress( job );
			if ( job.status === 'running' && ! state.processing ) {
				processLoop();
			}
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	async function startAudit() {
		const jobConfig = getJobConfig();
		if ( ! jobConfig.post_types.length || ! jobConfig.post_statuses.length ) {
			setFeedback( 'Hãy chọn ít nhất một loại nội dung và một trạng thái.', 'error' );
			return;
		}

		try {
			setFeedback( 'Đang khởi tạo tiến trình audit…' );
			const job = await request( '/audit/start', 'POST', jobConfig );
			renderProgress( job );
			setFeedback( 'Đã bắt đầu kiểm tra nội dung.', 'success' );
			processLoop();
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	async function processLoop() {
		if ( state.processing ) {
			return;
		}
		state.processing = true;

		try {
			while ( state.job && state.job.status === 'running' ) {
				try {
					const job = await request( '/audit/process', 'POST', {} );
					renderProgress( job );
					await refreshSummary();
					await refreshList();
					if ( job.status !== 'running' ) {
						setFeedback( job.status === 'completed' ? 'Audit đã hoàn tất.' : 'Tiến trình audit đã dừng.', job.status === 'completed' ? 'success' : 'info' );
						break;
					}
					await new Promise( ( resolve ) => window.setTimeout( resolve, 250 ) );
				} catch ( error ) {
					if ( error && Number( error.data && error.data.status ) === 409 ) {
						await new Promise( ( resolve ) => window.setTimeout( resolve, 1000 ) );
						continue;
					}
					setFeedback( error.message || config.labels.networkError, 'error' );
					break;
				}
			}
		} finally {
			state.processing = false;
		}
	}

	async function changeJobStatus( action ) {
		try {
			const job = await request( '/audit/' + action, 'POST', {} );
			renderProgress( job );
			setFeedback( action === 'pause' ? 'Đã tạm dừng audit.' : action === 'resume' ? 'Đã tiếp tục audit.' : 'Đã hủy audit.', 'info' );
			if ( action === 'resume' ) {
				processLoop();
			}
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	async function refreshSummary() {
		try {
			const summary = await request( '/audit/summary' );
			const items = [
				[ 'Tổng bài đã audit', summary.total ],
				[ 'Thiếu ảnh đại diện', summary.missing_featured ],
				[ 'Không có ảnh nội dung', summary.missing_content_images ],
				[ 'Có ảnh ngoài website', summary.has_external_images ],
				[ 'Có ảnh thiếu alt', summary.has_missing_alt ],
				[ 'Ưu tiên rất cao', summary.very_high_priority ],
				[ 'Có shortcode', summary.has_shortcode ],
				[ 'Có block phức tạp', summary.has_complex_blocks ],
				[ 'Bài lỗi', summary.errors ],
			];
			const container = $( '#ntci-summary' );
			container.replaceChildren();
			items.forEach( ( item ) => {
				const card = document.createElement( 'div' );
				card.className = 'ntci-stat';
				const value = document.createElement( 'strong' );
				value.textContent = Number( item[ 1 ] || 0 ).toLocaleString( 'vi-VN' );
				const label = document.createElement( 'span' );
				label.textContent = item[ 0 ];
				card.append( value, label );
				container.appendChild( card );
			} );
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	function addCell( row, value ) {
		const cell = document.createElement( 'td' );
		cell.textContent = value === null || value === undefined ? '' : String( value );
		row.appendChild( cell );
		return cell;
	}

	function priorityText( label ) {
		return { very_high: 'Rất cao', high: 'Cao', medium: 'Trung bình', low: 'Thấp' }[ label ] || label || '';
	}

	async function refreshList() {
		try {
			const result = await request( '/audit/posts?' + toQuery( getFilters() ) );
			const tbody = $( '#ntci-results-table tbody' );
			tbody.replaceChildren();

			if ( ! result.items || ! result.items.length ) {
				const row = document.createElement( 'tr' );
				const cell = addCell( row, config.labels.empty );
				cell.colSpan = 7;
				tbody.appendChild( row );
			} else {
				result.items.forEach( ( item ) => {
					const row = document.createElement( 'tr' );
					const titleCell = document.createElement( 'td' );
					const button = document.createElement( 'button' );
					button.type = 'button';
					button.className = 'button-link ntci-detail-button';
					button.dataset.postId = String( item.post_id );
					button.textContent = item.title || '(Không có tiêu đề)';
					titleCell.appendChild( button );
					row.appendChild( titleCell );
					addCell( row, item.post_type );
					addCell( row, Number( item.featured_image_id || 0 ) > 0 ? 'Có' : 'Thiếu' );
					addCell( row, Number( item.content_image_count || 0 ) );
					addCell( row, Number( item.word_count || 0 ).toLocaleString( 'vi-VN' ) );
					addCell( row, priorityText( item.priority_label ) + ' (' + Number( item.priority_score || 0 ) + ')' );
					addCell( row, item.audit_status );
					tbody.appendChild( row );
				} );
			}

			renderPagination( result );
			updateExportLink();
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	function renderPagination( result ) {
		const container = $( '#ntci-pagination' );
		container.replaceChildren();
		const totalPages = Math.max( 1, Math.ceil( Number( result.total || 0 ) / Number( result.per_page || state.perPage ) ) );
		const text = document.createElement( 'span' );
		text.textContent = 'Trang ' + Number( result.page || 1 ) + ' / ' + totalPages + ' · ' + Number( result.total || 0 ) + ' kết quả';
		const previous = document.createElement( 'button' );
		previous.type = 'button';
		previous.className = 'button';
		previous.textContent = 'Trước';
		previous.disabled = state.page <= 1;
		previous.addEventListener( 'click', () => { state.page -= 1; refreshList(); } );
		const next = document.createElement( 'button' );
		next.type = 'button';
		next.className = 'button';
		next.textContent = 'Sau';
		next.disabled = state.page >= totalPages;
		next.addEventListener( 'click', () => { state.page += 1; refreshList(); } );
		container.append( previous, text, next );
	}

	function updateExportLink() {
		const url = new URL( config.exportUrl, window.location.origin );
		Object.entries( getFilters() ).forEach( ( [ key, value ] ) => {
			if ( key !== 'page' && key !== 'per_page' && value !== '' ) {
				url.searchParams.set( key, String( value ) );
			}
		} );
		$( '#ntci-export' ).href = url.toString();
	}

	async function showDetail( postId ) {
		try {
			const item = await request( '/audit/posts/' + Number( postId ) );
			const detail = $( '#ntci-detail' );
			detail.replaceChildren();
			const fields = [
				[ 'Tiêu đề', item.title ],
				[ 'ID', item.post_id ],
				[ 'Loại / trạng thái', item.post_type + ' / ' + item.post_status ],
				[ 'Ảnh đại diện', Number( item.featured_image_id || 0 ) > 0 ? 'Có' : 'Thiếu' ],
				[ 'Ảnh nội dung', item.content_image_count ],
				[ 'Ảnh ngoài website', item.external_image_count ],
				[ 'Ảnh thiếu alt', item.missing_alt_count ],
				[ 'Số từ / H2 / H3', item.word_count + ' / ' + item.h2_count + ' / ' + item.h3_count ],
				[ 'Shortcode', ( item.analysis && item.analysis.shortcodes || [] ).join( ', ' ) || 'Không' ],
				[ 'Block cần bảo vệ', ( item.analysis && item.analysis.block_names || [] ).join( ', ' ) || 'Không' ],
				[ 'Yoast focus keyphrase', item.yoast_focus_keyphrase || 'Không có' ],
				[ 'Ưu tiên', priorityText( item.priority_label ) + ' (' + item.priority_score + ')' ],
			];
			const list = document.createElement( 'dl' );
			list.className = 'ntci-detail-list';
			fields.forEach( ( field ) => {
				const term = document.createElement( 'dt' );
				term.textContent = field[ 0 ];
				const description = document.createElement( 'dd' );
				description.textContent = field[ 1 ] === undefined ? '' : String( field[ 1 ] );
				list.append( term, description );
			} );
			detail.appendChild( list );
			$( '#ntci-detail-panel' ).hidden = false;
			$( '#ntci-detail-panel' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} catch ( error ) {
			setFeedback( error.message || config.labels.networkError, 'error' );
		}
	}

	$( '#ntci-start' ).addEventListener( 'click', startAudit );
	$( '#ntci-pause' ).addEventListener( 'click', () => changeJobStatus( 'pause' ) );
	$( '#ntci-resume' ).addEventListener( 'click', () => changeJobStatus( 'resume' ) );
	$( '#ntci-cancel' ).addEventListener( 'click', () => {
		if ( window.confirm( config.labels.confirmCancel ) ) {
			changeJobStatus( 'cancel' );
		}
	} );
	$( '#ntci-apply-filters' ).addEventListener( 'click', () => { state.page = 1; refreshList(); } );
	$( '#ntci-search' ).addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			state.page = 1;
			refreshList();
		}
	} );
	$( '#ntci-results-table' ).addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.ntci-detail-button' );
		if ( button ) {
			showDetail( button.dataset.postId );
		}
	} );
	$( '#ntci-detail-close' ).addEventListener( 'click', () => { $( '#ntci-detail-panel' ).hidden = true; } );

	Promise.all( [ refreshStatus(), refreshSummary(), refreshList() ] );
}() );
