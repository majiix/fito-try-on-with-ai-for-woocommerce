(function (wp) {
    if (!wp || !wp.blocks) {
        return;
    }
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, BlockControls, AlignmentToolbar } = wp.blockEditor || wp.editor;
    const { PanelBody, TextControl } = wp.components;
    const { createElement: el, Fragment } = wp.element;
    const { __ } = wp.i18n;

    registerBlockType('fito/tryon-button', {
        title: __('AI Try-On Button', 'fito-try-on-with-ai-for-woocommerce'),
        icon: 'camera',
        category: 'woocommerce',
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { buttonText, alignment } = attributes;

            return el(
                Fragment,
                null,
                el(
                    BlockControls,
                    null,
                    el(AlignmentToolbar, {
                        value: alignment,
                        onChange: function (newAlign) {
                            setAttributes({ alignment: newAlign || 'left' });
                        }
                    })
                ),
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Button Settings', 'fito-try-on-with-ai-for-woocommerce'), initialOpen: true },
                        el(TextControl, {
                            label: __('Button Label', 'fito-try-on-with-ai-for-woocommerce'),
                            value: buttonText,
                            onChange: function (newVal) {
                                setAttributes({ buttonText: newVal });
                            }
                        })
                    )
                ),
                el(
                    'div',
                    {
                        className: 'fito-block-editor-preview',
                        style: { textAlign: alignment, padding: '12px 0' }
                    },
                    el(
                        'button',
                        {
                            type: 'button',
                            className: 'fito-tryon-btn fito-tryon-btn--preview',
                            style: { pointerEvents: 'none' }
                        },
                        el(
                            'span',
                            { style: { marginRight: '8px' } },
                            '✨'
                        ),
                        el('span', null, buttonText || __('Try It On with AI', 'fito-try-on-with-ai-for-woocommerce'))
                    )
                )
            );
        },
        save: function () {
            // Rendered via shortcode or server hook
            return null;
        }
    });
})(window.wp);
