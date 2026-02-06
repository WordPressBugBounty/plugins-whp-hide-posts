(function (wp) {
	// Check if wp object exists
	if (!wp || !wp.plugins) {
		console.error('WHP: WordPress Gutenberg APIs not available');
		return;
	}

	const { registerPlugin } = wp.plugins;
	// Use wp.editor for WP 6.6+ or fallback to wp.editPost for older versions
	const { PluginDocumentSettingPanel } = wp.editor || wp.editPost;
	const { CheckboxControl, Button } = wp.components;
	const { useState, useEffect } = wp.element;
	const { useSelect, useDispatch } = wp.data;
	const { __ } = wp.i18n;

	/**
	 * Hide Posts Settings Panel for Gutenberg
	 */
	const HidePostsPanel = () => {
		const postId = useSelect((select) => select('core/editor').getCurrentPostId());
		const postType = useSelect((select) => select('core/editor').getCurrentPostType());

		// State for all checkboxes
		const [settings, setSettings] = useState({
			hide_on_frontpage: false,
			hide_on_categories: false,
			hide_on_search: false,
			hide_on_tags: false,
			hide_on_authors: false,
			hide_in_rss_feed: false,
			hide_on_blog_page: false,
			hide_on_date: false,
			hide_on_post_navigation: false,
			hide_on_recent_posts: false,
			hide_on_archive: false,
			hide_on_cpt_archive: false,
			hide_on_rest_api: false,
			hide_on_single_post_page: false,
			hide_on_xml_sitemap: false,
			hide_on_yoast_sitemap: false,
			hide_on_yoast_breadcrumbs: false,
			hide_on_yoast_internal_links: false,
			hide_on_store: false,
			hide_on_product_category: false,
		});

		const [loaded, setLoaded] = useState(false);
		const [saving, setSaving] = useState(false);

		// Load settings from custom table via REST API
		useEffect(() => {
			if (!postId || loaded) return;

			wp.apiFetch({
				path: `/whp/v1/hide-settings/${postId}`,
				method: 'GET',
			})
				.then((data) => {
					setSettings(data);
					setLoaded(true);
				})
				.catch((error) => {
					console.error('WHP: Error loading hide settings:', error);
					setLoaded(true);
				});
		}, [postId, loaded]);

		// Save settings to custom table when post is saved
		const { isSavingPost, isAutosavingPost } = useSelect((select) => ({
			isSavingPost: select('core/editor').isSavingPost(),
			isAutosavingPost: select('core/editor').isAutosavingPost(),
		}));

		useEffect(() => {
			// Only save on manual save, not autosave
			if (!loaded || !isSavingPost || isAutosavingPost || saving) return;

			setSaving(true);

			wp.apiFetch({
				path: `/whp/v1/hide-settings/${postId}`,
				method: 'POST',
				data: settings,
			})
				.then(() => {
					setSaving(false);
				})
				.catch((error) => {
					console.error('WHP: Error saving hide settings:', error);
					setSaving(false);
				});
		}, [isSavingPost, isAutosavingPost, settings, loaded, saving, postId]);

		// Update a single setting
		const updateSetting = (key, value) => {
			setSettings((prev) => ({
				...prev,
				[key]: value,
			}));
		};

		// Select all checkboxes
		const selectAll = () => {
			const allTrue = {};
			Object.keys(settings).forEach((key) => {
				allTrue[key] = true;
			});
			setSettings(allTrue);
		};

		// Deselect all checkboxes
		const deselectAll = () => {
			const allFalse = {};
			Object.keys(settings).forEach((key) => {
				allFalse[key] = false;
			});
			setSettings(allFalse);
		};

		// Check if all checkboxes are selected
		const allSelected = Object.values(settings).every((val) => val === true);
		const someSelected = Object.values(settings).some((val) => val === true);

		// Check if this is a custom post type
		const isCustomPostType = whpGutenberg && whpGutenberg.isCustomPostType;
		const isWooCommerceProduct = whpGutenberg && whpGutenberg.isWooCommerceProduct;
		const isYoastActive = whpGutenberg && whpGutenberg.isYoastActive;

		if (!loaded) {
			return wp.element.createElement('p', null, __('Loading...', 'whp-hide-posts'));
		}

		return wp.element.createElement(
			PluginDocumentSettingPanel,
			{
				name: 'hide-posts-panel',
				title: __('Hide Posts', 'whp-hide-posts'),
				className: 'whp-hide-posts-panel',
			},
			[
				// Select All / Deselect All buttons
				wp.element.createElement(
					'div',
					{ key: 'select-buttons', style: { marginBottom: '15px', display: 'flex', gap: '8px' } },
					[
						wp.element.createElement(Button, {
							key: 'select-all',
							variant: 'secondary',
							isSmall: true,
							onClick: selectAll,
							disabled: allSelected,
						}, __('Select All', 'whp-hide-posts')),
						wp.element.createElement(Button, {
							key: 'deselect-all',
							variant: 'secondary',
							isSmall: true,
							onClick: deselectAll,
							disabled: !someSelected,
						}, __('Deselect All', 'whp-hide-posts')),
					]
				),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_frontpage',
					label: __('Hide on frontpage', 'whp-hide-posts'),
					checked: settings.hide_on_frontpage,
					onChange: (value) => updateSetting('hide_on_frontpage', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_categories',
					label: __('Hide on categories', 'whp-hide-posts'),
					checked: settings.hide_on_categories,
					onChange: (value) => updateSetting('hide_on_categories', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_search',
					label: __('Hide on search', 'whp-hide-posts'),
					checked: settings.hide_on_search,
					onChange: (value) => updateSetting('hide_on_search', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_tags',
					label: __('Hide on tags page', 'whp-hide-posts'),
					checked: settings.hide_on_tags,
					onChange: (value) => updateSetting('hide_on_tags', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_authors',
					label: __('Hide on authors page', 'whp-hide-posts'),
					checked: settings.hide_on_authors,
					onChange: (value) => updateSetting('hide_on_authors', value),
				}),
				isCustomPostType && wp.element.createElement(CheckboxControl, {
					key: 'hide_on_cpt_archive',
					label: __('Hide on CPT archive page', 'whp-hide-posts'),
					checked: settings.hide_on_cpt_archive,
					onChange: (value) => updateSetting('hide_on_cpt_archive', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_date',
					label: __('Hide on date archive', 'whp-hide-posts'),
					checked: settings.hide_on_date,
					onChange: (value) => updateSetting('hide_on_date', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_archive',
					label: __('Hide on archive page', 'whp-hide-posts'),
					help: __('(This includes any custom taxonomy archive pages)', 'whp-hide-posts'),
					checked: settings.hide_on_archive,
					onChange: (value) => updateSetting('hide_on_archive', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_in_rss_feed',
					label: __('Hide in RSS Feed', 'whp-hide-posts'),
					checked: settings.hide_in_rss_feed,
					onChange: (value) => updateSetting('hide_in_rss_feed', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_blog_page',
					label: __('Hide on blog page', 'whp-hide-posts'),
					help: __('(The POSTS PAGE that is selected in Settings -> Reading)', 'whp-hide-posts'),
					checked: settings.hide_on_blog_page,
					onChange: (value) => updateSetting('hide_on_blog_page', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_post_navigation',
					label: __('Hide from post navigation', 'whp-hide-posts'),
					checked: settings.hide_on_post_navigation,
					onChange: (value) => updateSetting('hide_on_post_navigation', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_recent_posts',
					label: __('Hide from recent posts widget', 'whp-hide-posts'),
					checked: settings.hide_on_recent_posts,
					onChange: (value) => updateSetting('hide_on_recent_posts', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_rest_api',
					label: __('Hide from REST API', 'whp-hide-posts'),
					checked: settings.hide_on_rest_api,
					onChange: (value) => updateSetting('hide_on_rest_api', value),
				}),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_single_post_page',
					label: __('Hide on single post page', 'whp-hide-posts'),
					help: __('(This will hide post from recent posts, related posts and any other widget shown on the single post page)', 'whp-hide-posts'),
					checked: settings.hide_on_single_post_page,
					onChange: (value) => updateSetting('hide_on_single_post_page', value),
				}),
				wp.element.createElement('h4', { key: 'sitemap-title' }, __('Sitemap & SEO Options', 'whp-hide-posts')),
				wp.element.createElement(CheckboxControl, {
					key: 'hide_on_xml_sitemap',
					label: __('Hide from WordPress XML sitemap', 'whp-hide-posts'),
					help: __('(WordPress core sitemap)', 'whp-hide-posts'),
					checked: settings.hide_on_xml_sitemap,
					onChange: (value) => updateSetting('hide_on_xml_sitemap', value),
				}),
				isYoastActive && wp.element.createElement(CheckboxControl, {
					key: 'hide_on_yoast_sitemap',
					label: __('Hide from Yoast SEO sitemap', 'whp-hide-posts'),
					checked: settings.hide_on_yoast_sitemap,
					onChange: (value) => updateSetting('hide_on_yoast_sitemap', value),
				}),
				isYoastActive && wp.element.createElement(CheckboxControl, {
					key: 'hide_on_yoast_breadcrumbs',
					label: __('Hide from Yoast SEO breadcrumbs', 'whp-hide-posts'),
					checked: settings.hide_on_yoast_breadcrumbs,
					onChange: (value) => updateSetting('hide_on_yoast_breadcrumbs', value),
				}),
				isYoastActive && wp.element.createElement(CheckboxControl, {
					key: 'hide_on_yoast_internal_links',
					label: __('Hide from Yoast internal link suggestions', 'whp-hide-posts'),
					checked: settings.hide_on_yoast_internal_links,
					onChange: (value) => updateSetting('hide_on_yoast_internal_links', value),
				}),
				isWooCommerceProduct && wp.element.createElement('h4', { key: 'woo-title' }, __('WooCommerce options', 'whp-hide-posts')),
				isWooCommerceProduct && wp.element.createElement(CheckboxControl, {
					key: 'hide_on_store',
					label: __('Hide on shop page', 'whp-hide-posts'),
					checked: settings.hide_on_store,
					onChange: (value) => updateSetting('hide_on_store', value),
				}),
				isWooCommerceProduct && wp.element.createElement(CheckboxControl, {
					key: 'hide_on_product_category',
					label: __('Hide on product category page', 'whp-hide-posts'),
					checked: settings.hide_on_product_category,
					onChange: (value) => updateSetting('hide_on_product_category', value),
				}),
			]
		);
	};

	// Register the plugin
	registerPlugin('hide-posts-plugin', {
		render: HidePostsPanel,
		icon: 'hidden',
	});
})(window.wp);
