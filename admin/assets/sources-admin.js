(function () {
	'use strict';

	if (!window.wp || !window.wp.apiFetch || !window.NTContentImagesSources) {
		return;
	}

	var cfg = window.NTContentImagesSources;
	var apiFetch = window.wp.apiFetch;
	apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));

	var postSelect = document.getElementById('ntci-source-post');
	var providerSelect = document.getElementById('ntci-stock-provider');
	var queryInput = document.getElementById('ntci-stock-query');
	var searchButton = document.getElementById('ntci-stock-search');
	var results = document.getElementById('ntci-stock-results');
	var assets = document.getElementById('ntci-imported-assets');
	var feedback = document.getElementById('ntci-sources-feedback');
	var lastQuery = '';

	function setFeedback(message, type) {
		feedback.textContent = message || '';
		feedback.className = 'ntci-sources-feedback ' + (type ? 'is-' + type : '');
	}

	function request(path, options) {
		return apiFetch(Object.assign({ path: cfg.root + path }, options || {})).catch(function (error) {
			throw new Error(error && error.message ? error.message : cfg.labels.networkError);
		});
	}

	function loadCandidates() {
		return request('/generations/candidates?limit=50').then(function (data) {
			postSelect.textContent = '';
			var placeholder = document.createElement('option');
			placeholder.value = '';
			placeholder.textContent = data.items && data.items.length ? 'Chọn bài viết' : 'Không có bài thiếu ảnh đại diện';
			postSelect.appendChild(placeholder);
			(data.items || []).forEach(function (item) {
				var option = document.createElement('option');
				option.value = String(item.post_id);
				option.textContent = item.title + ' (#' + item.post_id + ')';
				postSelect.appendChild(option);
			});
		});
	}

	function externalLink(url, label) {
		var link = document.createElement('a');
		link.href = url;
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.textContent = label;
		return link;
	}

	function renderCandidate(item) {
		var card = document.createElement('article');
		card.className = 'ntci-stock-card';
		var image = document.createElement('img');
		image.src = item.preview_url;
		image.alt = item.title || '';
		image.loading = 'lazy';
		card.appendChild(image);

		var body = document.createElement('div');
		body.className = 'ntci-stock-card-body';
		var title = document.createElement('strong');
		title.textContent = item.title || 'Ảnh không có tiêu đề';
		body.appendChild(title);
		var meta = document.createElement('p');
		meta.textContent = (item.creator_name || 'Không rõ tác giả') + ' · ' + String(item.license_code || '').toUpperCase() + ' · ' + (item.width || 0) + '×' + (item.height || 0);
		body.appendChild(meta);
		if (item.attribution) {
			var attribution = document.createElement('p');
			attribution.className = 'ntci-attribution';
			attribution.textContent = item.attribution;
			body.appendChild(attribution);
		}

		var confirmation = null;
		if (item.requires_license_confirmation) {
			var label = document.createElement('label');
			label.className = 'ntci-license-confirm';
			confirmation = document.createElement('input');
			confirmation.type = 'checkbox';
			label.appendChild(confirmation);
			label.appendChild(document.createTextNode(' Đã kiểm tra giấy phép'));
			body.appendChild(label);
		}

		var actions = document.createElement('div');
		actions.className = 'ntci-stock-actions';
		actions.appendChild(externalLink(item.source_page_url, 'Xem nguồn'));
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'button button-primary';
		button.textContent = 'Chọn ảnh';
		button.addEventListener('click', function () {
			if (confirmation && !confirmation.checked) {
				window.alert(cfg.labels.licenseConfirm);
				return;
			}
			if (!window.confirm(cfg.labels.importConfirm)) {
				return;
			}
			button.disabled = true;
			button.textContent = 'Đang nhập…';
			request('/sources/import', {
				method: 'POST',
				data: {
					post_id: Number(postSelect.value),
					provider: providerSelect.value,
					asset_id: item.asset_id,
					search_query: lastQuery,
					license_confirmed: confirmation ? confirmation.checked : true
				}
			}).then(function () {
				setFeedback('Đã nhập ảnh vào Media Library và đưa vào danh sách chờ duyệt.', 'success');
				button.textContent = 'Đã nhập';
				loadAssets();
				loadCandidates();
			}).catch(function (error) {
				button.disabled = false;
				button.textContent = 'Chọn ảnh';
				setFeedback(error.message, 'error');
			});
		});
		actions.appendChild(button);
		body.appendChild(actions);
		card.appendChild(body);
		return card;
	}

	function doSearch() {
		var postId = Number(postSelect.value || 0);
		if (!postId) {
			setFeedback('Hãy chọn một bài viết.', 'error');
			return;
		}
		searchButton.disabled = true;
		results.textContent = '';
		setFeedback('Đang phân tích bài và tìm ảnh…', 'loading');
		var path = '/sources/search?post_id=' + encodeURIComponent(postId) + '&provider=' + encodeURIComponent(providerSelect.value) + '&query=' + encodeURIComponent(queryInput.value || '');
		request(path).then(function (data) {
			lastQuery = data.query || data.suggested_query || '';
			if (!queryInput.value && lastQuery) {
				queryInput.value = lastQuery;
			}
			(data.items || []).forEach(function (item) {
				results.appendChild(renderCandidate(item));
			});
			setFeedback((data.items || []).length ? 'Đã tìm thấy ' + data.items.length + ' ảnh. Hãy kiểm tra nguồn trước khi chọn.' : 'Không tìm thấy ảnh phù hợp. Có thể đổi từ khóa hoặc dùng AI.', (data.items || []).length ? 'success' : 'warning');
		}).catch(function (error) {
			setFeedback(error.message, 'error');
		}).finally(function () {
			searchButton.disabled = false;
		});
	}

	function loadAssets() {
		return request('/sources/assets?limit=20').then(function (data) {
			assets.textContent = '';
			(data.items || []).forEach(function (item) {
				var card = document.createElement('article');
				card.className = 'ntci-stock-card is-imported';
				if (item.image_url) {
					var image = document.createElement('img');
					image.src = item.image_url;
					image.alt = item.title || '';
					image.loading = 'lazy';
					card.appendChild(image);
				}
				var body = document.createElement('div');
				body.className = 'ntci-stock-card-body';
				var title = document.createElement('strong');
				title.textContent = item.title || 'Ảnh đã nhập';
				body.appendChild(title);
				var meta = document.createElement('p');
				meta.textContent = item.provider + ' · ' + item.status + ' · ' + (item.attribution_text || '');
				body.appendChild(meta);
				card.appendChild(body);
				assets.appendChild(card);
			});
			if (!(data.items || []).length) {
				assets.textContent = 'Chưa có ảnh kho nào được nhập.';
			}
		});
	}

	if (searchButton) {
		searchButton.addEventListener('click', doSearch);
	}
	Promise.all([loadCandidates(), loadAssets()]).catch(function (error) {
		setFeedback(error.message, 'error');
	});
}());
