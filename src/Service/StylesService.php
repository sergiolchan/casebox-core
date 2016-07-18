<?php

namespace Casebox\CoreBundle\Service;

/**
 * Class StylesService
 */
class StylesService
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var array
     */
    protected $styles;

    /**
     * @return array
     */
    public function getStyles()
    {
        return $this->styles;
    }

    /**
     * @param array $styles
     *
     * @return StylesService $this
     */
    public function setStyles(array $styles)
    {
        if (empty($this->styles)) {
            $this->styles = $styles;
        } else {
            $this->styles = array_merge($this->styles, $styles);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getDefault()
    {
        $styles = [
            'ext-theme-triton-all' => [
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => '/js/ext/classic/theme-triton/resources/theme-triton-all.css',
            ],
            // 'extjs-ace-styles' => [
            //     'rel' => 'stylesheet',
            //     'type' => 'text/css',
            //     'href' => '/js/extjs-ace/styles.css',
            // ],
            'caseboxindex' => [
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => '/css/caseboxindex.css',
            ],
            'leaflet' => [
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'href' => '/js/leaflet/leaflet.css',
            ],
        ];

        $container = Cache::get('symfony.container');
        if (!empty($container) && $container->hasParameter('devel')) {

            $groups = $container->get('casebox_core.service.minify')->getDefaultAssests();
            foreach ($groups['css'] as $script) {
                $styles[$script] = [
                    'rel' => 'stylesheet',
                    'type' => 'text/css',
                    'href' => '/' . $script
                ];
            }

        } else {
            $styles = array_merge(
                $styles,
                [
                    'min-css' => [
                        'rel' => 'stylesheet',
                        'type' => 'text/css',
                        'href' => '/min/css.css',
                    ],
                ]
            );
        }

        return $styles;
    }

    /**
     * @return string
     */
    public function getRendered()
    {
        $html = '';

        if (!empty($this->styles)) {
            $i = $weight = 0;
            foreach ($this->styles as $style) {
                if (empty($style['href'])) {
                    continue;
                }

                if (empty($style['rel'])) {
                    $style['rel'] = 'stylesheet';
                }

                if (empty($style['type'])) {
                    $style['type'] = 'text/css';
                }

                if (empty($style['weight'])) {
                    $style['weight'] = $i;
                }

                $ords[(string) $style['weight']] = $this->getTwig()->render(
                    'CaseboxCoreBundle:render:style_render.html.twig',
                    $style
                );

                $i++;
            }

            ksort($ords);

            $html = implode('', $ords);

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
     * @return StylesService $this
     */
    public function setTwig(\Twig_Environment $twig)
    {
        $this->twig = $twig;

        return $this;
    }
}
