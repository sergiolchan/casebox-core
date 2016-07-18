<?php
namespace Casebox\CoreBundle\Service;

use Casebox\CoreBundle\Service\Cache;

/**
 * Class JavascriptService
 */
class JavascriptService
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var array
     */
    protected $scripts;

    /**
     * @return array
     */
    public function getScripts()
    {
        return $this->scripts;
    }

    /**
     * @param array $scripts
     *
     * @return JavascriptService $this
     */
    public function setScripts(array $scripts)
    {
        if (empty($this->scripts)) {
            $this->scripts = $scripts;
        } else {
            foreach ($this->scripts as $group => $script) {
                if (!empty($scripts[$group])) {
                    $this->scripts[$group] = array_merge($this->scripts[$group], $scripts[$group]);
                }
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getDefault()
    {
        $scripts = [
            'header' => [
                'leaflet' => [
                    'src' => '/js/leaflet/leaflet.js',
                ],
                'progress' => [
                    'inline' => "window.name = '0fe6c741a69ef4b56882f0e';function setProgress(label, percentage) {document.getElementById('loading-msg').innerHTML = label + '…';document.getElementById('lpt').style.width = percentage;}",
                ],
            ],
            'footer' => [
                'progress-30' => [
                    'inline' => "setProgress('{{ 'Loading_ExtJS_Core'|trans }}', '30%')",
                ],
                'ext-all-debug' => [
                    'src' => '/js/ext/ext-all-debug.js',
                ],
                'ext-charts-debug' => [
                    'src' => '/js/ext/packages/charts/classic/charts.js',
                ],
                'ext-theme-triton-debug' => [
                    'src' => '/js/ext/classic/theme-triton/theme-triton.js',
                ],

                'ace-editor' => [
                    'src' => '/js/jarvus-ace-master/resources/ace-builds-master/src-min-noconflict/ace.js ',
                ],
                'jarvus-config0' => [
                    'src' => '/js/jarvus-ace-master/classic/src/ace/util/AbstractConfiguration.js',
                ],
                'jarvus-config1' => [
                    'src' => '/js/jarvus-ace-master/classic/src/ace/util/Configuration.js',
                ],
                'jarvus-wrapper' => [
                    'src' => '/js/jarvus-ace-master/src/ace/Editor.js',
                ],
                'l' => [
                    'inline' => "if (typeof(L) !== 'undefined') { LL = L; }",
                ],
                'ext-locale' => [
                    'src' => '/js/ext/classic/locale/locale-{{ app.request.locale }}.js',
                ],
                'highlight' => [
                    'src' => '/js/highlight/highlight.pack.js',
                ],
                'progress-60' => [
                    'inline' => "setProgress('{{ 'Loading_ExtJS_UI'|trans }}', '60%')",
                ],
                'remote-api' => [
                    'src' => '/c/{{ coreName }}/remote/api',
                ],
                'locale' => [
                    'src' => '/min/locale/{{ app.request.locale }}.js',
                ],
            ],
        ];

        $container = Cache::get('symfony.container');
        if (!empty($container) && $container->hasParameter('devel')) {
            $groups = $container->get('casebox_core.service.minify')->getDefaultAssests();
            $addGroups = ['js', 'jsoverrides', 'jsplugins'];
            foreach ($addGroups as $group) {
                foreach ($groups[$group] as $script) {
                    $scripts['footer'][$script]['src'] = '/' . $script;
                }
            }
        } else {
            $scripts['footer'] = array_merge(
                $scripts['footer'],
                [
                    'js' => [
                        'src' => '/min/js-debug.js',
                    ],
                    'jsoverrides' => [
                        'src' => '/min/jsoverrides-debug.js',
                    ],
                    'jsplugins' => [
                        'src' => '/min/jsplugins-debug.js',
                    ],
                ]
            );
        }

        $scripts['footer']['CB_Browser_SearchRouter'] = [
            'inline' => "CB.plugin.config = {'Search': {'handler': 'CB_Browser_SearchRouter.search'}};",
        ];

        $scripts['footer']['db_js'] = [
            'src' => '/c/{{ coreName }}/js/DB.js',
        ];

        $scripts['footer']['progress-100'] = [
            'inline' => "setProgress('{{ 'Initialization'|trans }}', '100%')",
        ];

        return $scripts;
    }

    /**
     * @param array $vars
     *
     * @return string
     */
    public function getRendered(array $vars = [])
    {
        $html = [
            'header' => '',
            'footer' => '',
        ];

        if (!empty($this->scripts)) {
            foreach ($this->scripts as $group => $sripts) {
                $i = 0;
                foreach ($sripts as $script) {
                    if (empty($script['src']) && empty($script['inline'])) {
                        continue;
                    }

                    if (empty($script['type'])) {
                        $script['type'] = 'text/javascript';
                    }

                    if (empty($script['weight'])) {
                        $script['weight'] = $i;
                    }

                    if (!empty($script['inline'])) {
                        $template = $this->getTwig()->createTemplate($script['inline']);
                        $script['inline'] = $template->render($vars);

                        $ords[(string) $script['weight']] = $this->getTwig()->render(
                            'CaseboxCoreBundle:render:javascript_inline_render.html.twig',
                            $script
                        );
                    } else {
                        $template = $this->getTwig()->createTemplate($script['src']);
                        $script['src'] = $template->render($vars);

                        $ords[(string) $script['weight']] = $this->getTwig()->render(
                            'CaseboxCoreBundle:render:javascript_render.html.twig',
                            $script
                        );
                    }

                    $i++;
                }

                ksort($ords);

                $html[$group] = implode('', $ords);
            }
        }

        return $html;
    }

    /**
     * @return \Twig_Environment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * @param \Twig_Environment $twig
     *
     * @return JavascriptService $this
     */
    public function setTwig(\Twig_Environment $twig)
    {
        $this->twig = $twig;

        return $this;
    }
}
