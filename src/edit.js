/**
 * External dependencies
 */
import { useMemo } from "@wordpress/element";
import { InspectorControls } from "@wordpress/block-editor";
import { PanelBody, PanelRow, Notice } from "@wordpress/components";
import { addFilter } from "@wordpress/hooks";
import { __ } from "@wordpress/i18n";

/**
 * Internal dependencies
 */
import { TaxonomyControls } from "./components/tax-selector";
import { CURRENT_TERM_ID } from "./constants";

/**
 * Add the taxonomy controls to the block editor.
 *
 * @param {Function} BlockEdit The original block edit component.
 * @return {Function} The new block edit component.
 */
export const withCurrentTaxonomyQueryControls = (BlockEdit) => (props) => {
	const { attributes, setAttributes } = props;

	if (
		attributes?.namespace !== "tax-context-query-loop/query-loop" ||
		attributes.query.postType !== "post"
	) {
		return <BlockEdit key="edit" {...props} />;
	}

	// Has Contextual Features?
	const taxQuery = attributes.query.taxQuery ?? {};
	const hasContextualFeatures = useMemo(() => {
		if (!taxQuery) {
			return false;
		}

		if (Object.keys(taxQuery).length === 0) {
			return false;
		}

		const has = Object.values(taxQuery).reduce((acc, termIds) => {
			if (termIds.includes(CURRENT_TERM_ID)) {
				acc = true;
			}
			return acc;
		}, false);

		return has;
	}, [taxQuery]);

	const updateQuery = (newQuery) =>
		setAttributes({ query: { ...attributes.query, ...newQuery } });

	return (
		<>
			<BlockEdit key="edit" {...props} />
			<InspectorControls>
				<PanelBody
					title={__("Taxonomies", "tax-context-query-loop")}
					initialOpen={true}
				>
					<TaxonomyControls onChange={updateQuery} query={attributes.query} />
					{hasContextualFeatures && (
						<PanelRow>
							<Notice status="info" isDismissible={false}>
								{__(
									"The products seen by shoppers will vary depending on the viewed product.",
									"tax-context-query-loop",
								)}
							</Notice>
						</PanelRow>
					)}
				</PanelBody>
			</InspectorControls>
		</>
	);
};

addFilter("editor.BlockEdit", "core/query", withCurrentTaxonomyQueryControls);
