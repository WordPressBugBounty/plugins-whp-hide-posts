(function ($) {
	$(function () {
		// Select All checkbox functionality
		const $selectAll = $("#whp_select_all");

		if ($selectAll.length > 0) {
			const totalChecked = $(
				"input[type=checkbox][id^=whp_hide_]:checked"
			).length;
			const totalOptions = $(
				"input[type=checkbox][id^=whp_hide_]"
			).length;

			if (totalChecked === totalOptions) {
				$selectAll.prop("checked", true);
			}

			const toggleAllOptions = function () {
				if ($(this).is(":checked")) {
					$("input[type=checkbox][id^=whp_hide_]").prop(
						"checked",
						true
					);
				} else {
					$("input[type=checkbox][id^=whp_hide_]").prop(
						"checked",
						false
					);
				}
			};

			$selectAll.on("change", toggleAllOptions);
		}

		// Quick Edit functionality
		$(document).on("click", ".editinline", function () {
			const postId = $(this).closest("tr").attr("id").replace("post-", "");
			const $row = $("#post-" + postId);

			// Get current hide values from hidden spans or data attributes
			const hideFrontpage = $row.find(
				".whp-hide-frontpage-value"
			).text();
			const hideCategories = $row.find(
				".whp-hide-categories-value"
			).text();
			const hideSearch = $row.find(".whp-hide-search-value").text();

			// Set checkboxes in quick edit
			setTimeout(function () {
				const $editRow = $("#edit-" + postId);
				$editRow
					.find('input[name="whp_hide_on_frontpage"]')
					.prop("checked", hideFrontpage === "1");
				$editRow
					.find('input[name="whp_hide_on_categories"]')
					.prop("checked", hideCategories === "1");
				$editRow
					.find('input[name="whp_hide_on_search"]')
					.prop("checked", hideSearch === "1");
			}, 100);
		});

		// Bulk Edit - Apply button
		$(document).on("click", "#bulk_edit", function (e) {
			const $bulkRow = $("#bulk-edit");
			const action = $bulkRow.find('select[name="whp_bulk_hide_action"]').val();

			if (!action) {
				return; // No action selected
			}

			// Get all selected post IDs
			const postIds = [];
			$bulkRow.closest("table").find('tbody input[name="post[]"]:checked').each(function () {
				postIds.push($(this).val());
			});

			if (postIds.length === 0) {
				return;
			}

			// Send AJAX request
			$.ajax({
				url: ajaxurl,
				type: "POST",
				data: {
					action: "whp_bulk_edit_save",
					nonce: whpPlugin.bulk_edit_nonce,
					post_ids: postIds,
					hide_action: action,
				},
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert(
							response.data.message ||
								"Error updating posts"
						);
					}
				},
				error: function () {
					alert("Error communicating with server");
				},
			});
		});
	});
})(jQuery);
