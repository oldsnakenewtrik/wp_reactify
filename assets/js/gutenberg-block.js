/**
 * ReactifyWP Gutenberg Block
 */

(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment, useState, useEffect } = wp.element;
    const { 
        InspectorControls, 
        BlockControls,
        useBlockProps,
        AlignmentToolbar 
    } = wp.blockEditor;
    const { 
        PanelBody, 
        SelectControl, 
        TextControl, 
        ToggleControl, 
        RangeControl,
        Button,
        Placeholder,
        Spinner,
        Notice,
        Card,
        CardBody,
        CardHeader,
        __experimentalDivider as Divider
    } = wp.components;
    const { __ } = wp.i18n;
    const { apiFetch } = wp;

    // Block icon
    const blockIcon = el('svg', {
        width: 24,
        height: 24,
        viewBox: '0 0 24 24',
        fill: 'none',
        xmlns: 'http://www.w3.org/2000/svg'
    }, [
        el('path', {
            key: 'path1',
            d: 'M12 2L2 7L12 12L22 7L12 2Z',
            stroke: 'currentColor',
            strokeWidth: '2',
            strokeLinejoin: 'round'
        }),
        el('path', {
            key: 'path2',
            d: 'M2 17L12 22L22 17',
            stroke: 'currentColor',
            strokeWidth: '2',
            strokeLinejoin: 'round'
        }),
        el('path', {
            key: 'path3',
            d: 'M2 12L12 17L22 12',
            stroke: 'currentColor',
            strokeWidth: '2',
            strokeLinejoin: 'round'
        })
    ]);

    // Register the block
    registerBlockType('reactifywp/react-app', {
        title: ReactifyWPBlock.i18n.blockTitle,
        description: ReactifyWPBlock.i18n.blockDescription,
        icon: blockIcon,
        category: 'widgets',
        keywords: ['react', 'app', 'spa', 'javascript'],
        supports: {
            align: ['left', 'center', 'right', 'wide', 'full'],
            anchor: true,
            className: true,
            spacing: {
                margin: true,
                padding: true
            },
            color: {
                background: true,
                text: true,
                gradients: true
            }
        },
        attributes: {
            projectSlug: {
                type: 'string',
                default: ''
            },
            width: {
                type: 'string',
                default: '100%'
            },
            height: {
                type: 'string',
                default: 'auto'
            },
            loading: {
                type: 'string',
                default: 'auto'
            },
            fallback: {
                type: 'string',
                default: ''
            },
            errorBoundary: {
                type: 'boolean',
                default: true
            },
            debug: {
                type: 'boolean',
                default: false
            },
            containerId: {
                type: 'string',
                default: ''
            },
            theme: {
                type: 'string',
                default: 'default'
            },
            responsive: {
                type: 'boolean',
                default: true
            },
            preload: {
                type: 'boolean',
                default: false
            },
            ssr: {
                type: 'boolean',
                default: false
            },
            props: {
                type: 'string',
                default: '{}'
            },
            config: {
                type: 'string',
                default: '{}'
            },
            cache: {
                type: 'boolean',
                default: true
            },
            alignment: {
                type: 'string',
                default: 'none'
            }
        },

        edit: function(props) {
            const { attributes, setAttributes, isSelected } = props;
            const {
                projectSlug,
                width,
                height,
                loading,
                fallback,
                errorBoundary,
                debug,
                containerId,
                theme,
                responsive,
                preload,
                ssr,
                props: propsJson,
                config: configJson,
                cache,
                alignment
            } = attributes;

            const [projects, setProjects] = useState(ReactifyWPBlock.projects || []);
            const [projectPreview, setProjectPreview] = useState(null);
            const [isLoadingPreview, setIsLoadingPreview] = useState(false);
            const [previewError, setPreviewError] = useState(null);

            const blockProps = useBlockProps({
                className: `align${alignment}`
            });

            // Load project preview when project changes
            useEffect(() => {
                if (projectSlug && projectSlug !== '') {
                    loadProjectPreview(projectSlug);
                } else {
                    setProjectPreview(null);
                }
            }, [projectSlug]);

            // Load project preview
            const loadProjectPreview = async (slug) => {
                setIsLoadingPreview(true);
                setPreviewError(null);

                try {
                    const formData = new FormData();
                    formData.append('action', 'reactifywp_preview_project');
                    formData.append('nonce', ReactifyWPBlock.nonce);
                    formData.append('project_slug', slug);

                    const response = await fetch(ReactifyWPBlock.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        setProjectPreview(result.data);
                    } else {
                        setPreviewError(result.data || 'Failed to load preview');
                    }
                } catch (error) {
                    setPreviewError('Network error loading preview');
                } finally {
                    setIsLoadingPreview(false);
                }
            };

            // Render project selector
            const renderProjectSelector = () => {
                if (projects.length === 0) {
                    return el(Notice, {
                        status: 'warning',
                        isDismissible: false
                    }, ReactifyWPBlock.i18n.noProjects);
                }

                return el(SelectControl, {
                    label: ReactifyWPBlock.i18n.selectProject,
                    value: projectSlug,
                    options: [
                        { value: '', label: '— Select Project —' },
                        ...projects.map(project => ({
                            value: project.value,
                            label: project.label
                        }))
                    ],
                    onChange: (value) => setAttributes({ projectSlug: value })
                });
            };

            // Render project preview
            const renderProjectPreview = () => {
                if (!projectSlug) {
                    return null;
                }

                if (isLoadingPreview) {
                    return el('div', { className: 'reactifywp-block-loading' }, [
                        el(Spinner, { key: 'spinner' }),
                        el('p', { key: 'text' }, ReactifyWPBlock.i18n.loading)
                    ]);
                }

                if (previewError) {
                    return el(Notice, {
                        status: 'error',
                        isDismissible: false
                    }, previewError);
                }

                if (projectPreview) {
                    return el(Card, { className: 'reactifywp-project-preview' }, [
                        el(CardHeader, { key: 'header' }, [
                            el('h4', { key: 'title' }, projectPreview.name),
                            el('span', { 
                                key: 'status',
                                className: `status status-${projectPreview.status}`
                            }, projectPreview.status)
                        ]),
                        el(CardBody, { key: 'body' }, [
                            el('p', { key: 'description' }, projectPreview.description),
                            el('div', { key: 'meta', className: 'project-meta' }, [
                                el('span', { key: 'version' }, `Version: ${projectPreview.version}`),
                                el('span', { key: 'files' }, `Files: ${projectPreview.file_count}`),
                                el('span', { key: 'size' }, `Size: ${projectPreview.size}`),
                                el('span', { key: 'framework' }, `Framework: ${projectPreview.framework}`)
                            ])
                        ])
                    ]);
                }

                return null;
            };

            // Render block placeholder
            const renderPlaceholder = () => {
                return el(Placeholder, {
                    icon: blockIcon,
                    label: ReactifyWPBlock.i18n.blockTitle,
                    instructions: ReactifyWPBlock.i18n.selectProject
                }, [
                    renderProjectSelector(),
                    renderProjectPreview()
                ]);
            };

            // Render block preview
            const renderBlockPreview = () => {
                const previewStyles = {
                    width: width !== '100%' ? width : undefined,
                    height: height !== 'auto' ? height : undefined,
                    minHeight: '200px',
                    border: '2px dashed #ddd',
                    borderRadius: '4px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    backgroundColor: '#f9f9f9',
                    position: 'relative'
                };

                return el('div', {
                    style: previewStyles,
                    className: 'reactifywp-block-preview'
                }, [
                    el('div', { 
                        key: 'content',
                        className: 'preview-content'
                    }, [
                        el('div', { key: 'icon', className: 'preview-icon' }, blockIcon),
                        el('h4', { key: 'title' }, projectPreview?.name || 'React App'),
                        el('p', { key: 'description' }, `Project: ${projectSlug}`),
                        el('small', { key: 'note' }, 'Preview in editor - actual app will render on frontend')
                    ]),
                    isSelected && el('div', {
                        key: 'overlay',
                        className: 'preview-overlay'
                    }, 'Click to edit settings')
                ]);
            };

            // Inspector controls
            const inspectorControls = el(InspectorControls, {}, [
                // Project Settings
                el(PanelBody, {
                    key: 'project-settings',
                    title: ReactifyWPBlock.i18n.projectSettings,
                    initialOpen: true
                }, [
                    renderProjectSelector(),
                    renderProjectPreview()
                ]),

                // Display Settings
                el(PanelBody, {
                    key: 'display-settings',
                    title: ReactifyWPBlock.i18n.displaySettings,
                    initialOpen: false
                }, [
                    el(TextControl, {
                        key: 'width',
                        label: 'Width',
                        value: width,
                        onChange: (value) => setAttributes({ width: value }),
                        help: 'CSS width value (e.g., 100%, 500px, auto)'
                    }),
                    el(TextControl, {
                        key: 'height',
                        label: 'Height',
                        value: height,
                        onChange: (value) => setAttributes({ height: value }),
                        help: 'CSS height value (e.g., auto, 400px, 100vh)'
                    }),
                    el(SelectControl, {
                        key: 'theme',
                        label: 'Theme',
                        value: theme,
                        options: ReactifyWPBlock.themes,
                        onChange: (value) => setAttributes({ theme: value })
                    }),
                    el(ToggleControl, {
                        key: 'responsive',
                        label: 'Responsive',
                        checked: responsive,
                        onChange: (value) => setAttributes({ responsive: value }),
                        help: 'Make the app responsive to screen size'
                    })
                ]),

                // Advanced Settings
                el(PanelBody, {
                    key: 'advanced-settings',
                    title: ReactifyWPBlock.i18n.advancedSettings,
                    initialOpen: false
                }, [
                    el(SelectControl, {
                        key: 'loading',
                        label: 'Loading Strategy',
                        value: loading,
                        options: [
                            { value: 'auto', label: 'Auto' },
                            { value: 'lazy', label: 'Lazy' },
                            { value: 'eager', label: 'Eager' }
                        ],
                        onChange: (value) => setAttributes({ loading: value })
                    }),
                    el(TextControl, {
                        key: 'fallback',
                        label: 'Fallback Content',
                        value: fallback,
                        onChange: (value) => setAttributes({ fallback: value }),
                        help: 'HTML to show while loading or on error'
                    }),
                    el(TextControl, {
                        key: 'containerId',
                        label: 'Container ID',
                        value: containerId,
                        onChange: (value) => setAttributes({ containerId: value }),
                        help: 'Custom ID for the container element'
                    }),
                    el(Divider, { key: 'divider1' }),
                    el(ToggleControl, {
                        key: 'errorBoundary',
                        label: 'Error Boundary',
                        checked: errorBoundary,
                        onChange: (value) => setAttributes({ errorBoundary: value }),
                        help: 'Show user-friendly errors instead of breaking'
                    }),
                    el(ToggleControl, {
                        key: 'preload',
                        label: 'Preload Assets',
                        checked: preload,
                        onChange: (value) => setAttributes({ preload: value }),
                        help: 'Preload app assets for faster loading'
                    }),
                    el(ToggleControl, {
                        key: 'cache',
                        label: 'Enable Caching',
                        checked: cache,
                        onChange: (value) => setAttributes({ cache: value }),
                        help: 'Cache app assets for better performance'
                    }),
                    el(ToggleControl, {
                        key: 'ssr',
                        label: 'Server-Side Rendering',
                        checked: ssr,
                        onChange: (value) => setAttributes({ ssr: value }),
                        help: 'Enable SSR if supported by the app'
                    }),
                    el(ToggleControl, {
                        key: 'debug',
                        label: 'Debug Mode',
                        checked: debug,
                        onChange: (value) => setAttributes({ debug: value }),
                        help: 'Show debug information (admin only)'
                    }),
                    el(Divider, { key: 'divider2' }),
                    el(TextControl, {
                        key: 'props',
                        label: 'Props (JSON)',
                        value: propsJson,
                        onChange: (value) => setAttributes({ props: value }),
                        help: 'JSON object with props to pass to the app'
                    }),
                    el(TextControl, {
                        key: 'config',
                        label: 'Config (JSON)',
                        value: configJson,
                        onChange: (value) => setAttributes({ config: value }),
                        help: 'JSON object with configuration options'
                    })
                ])
            ]);

            // Block controls
            const blockControls = el(BlockControls, {}, [
                el(AlignmentToolbar, {
                    key: 'alignment',
                    value: alignment,
                    onChange: (value) => setAttributes({ alignment: value || 'none' })
                })
            ]);

            // Main render
            return el(Fragment, {}, [
                inspectorControls,
                blockControls,
                el('div', blockProps, 
                    !projectSlug ? renderPlaceholder() : renderBlockPreview()
                )
            ]);
        },

        save: function() {
            // Return null since we use render_callback
            return null;
        }
    });

})();
