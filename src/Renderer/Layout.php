<?php
/*
 * Copyright (c) Arnaud Ligny <arnaud@ligny.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPoole\Renderer;

use PHPoole\Collection\Page\Page;
use PHPoole\Config;
use PHPoole\Exception\Exception;
use PHPoole\Page\NodeType;
use PHPoole\Util;

/**
 * Class Layout.
 */
class Layout
{
    /**
     * Layout file finder.
     *
     * @param Page   $page
     * @param Config $config
     *
     * @throws Exception
     *
     * @return string
     */
    public function finder(Page $page, Config $config)
    {
        if ($page->getLayout() == 'redirect.html') {
            return $page->getLayout().'.twig';
        }

        $layout = 'unknown';
        $layouts = self::fallback($page);

        // is layout exists in local layout dir?
        foreach ($layouts as $layout) {
            if (Util::getFS()->exists($config->getLayoutsPath().'/'.$layout)) {
                return $layout;
            }
        }
        // is layout exists in layout theme dir?
        if ($config->hasTheme()) {
            foreach ($layouts as $layout) {
                if (Util::getFS()->exists($config->getThemePath($config->get('theme')).'/'.$layout)) {
                    return $layout;
                }
            }
        }

        throw new Exception(sprintf("Layout '%s' not found for page '%s'!", $layout, $page->getId()));
    }

    /**
     * Layout fall-back.
     *
     * @param $page
     *
     * @return string[]
     *
     * @see finder()
     */
    protected static function fallback(Page $page)
    {
        // remove redundant '.twig' extension
        $layout = str_replace('.twig', '', $page->getLayout());

        switch ($page->getNodeType()) {
            case NodeType::HOMEPAGE:
                $layouts = [
                    'index.html.twig',
                    '_default/list.html.twig',
                    '_default/page.html.twig',
                ];
                break;
            case NodeType::SECTION:
                $layouts = [
                    // 'section/$section.html.twig',
                    '_default/section.html.twig',
                    '_default/list.html.twig',
                ];
                if ($page->getPathname()) {
                    $section = explode('/', $page->getPathname())[0];
                    $layouts = array_merge(
                        [sprintf('section/%s.html.twig', $section)],
                        $layouts
                    );
                }
                break;
            case NodeType::TAXONOMY:
                $layouts = [
                    // 'taxonomy/$singular.html.twig',
                    '_default/taxonomy.html.twig',
                    '_default/list.html.twig',
                ];
                if ($page->getVariable('singular')) {
                    $layouts = array_merge(
                        [sprintf('taxonomy/%s.html.twig', $page->getVariable('singular'))],
                        $layouts
                    );
                }
                break;
            case NodeType::TERMS:
                $layouts = [
                    // 'taxonomy/$singular.terms.html.twig',
                    '_default/terms.html.twig',
                ];
                if ($page->getVariable('singular')) {
                    $layouts = array_merge(
                        [sprintf('taxonomy/%s.terms.html.twig', $page->getVariable('singular'))],
                        $layouts
                    );
                }
                break;
            default:
                $layouts = [
                    // '$section/page.html.twig',
                    // '$section/$layout.twig',
                    // '$layout.twig',
                    // 'page.html.twig',
                    '_default/page.html.twig',
                ];
                if ($page->getSection()) {
                    $layouts = array_merge(
                        [sprintf('%s/page.html.twig', $page->getSection())],
                        $layouts
                    );
                    if ($page->getLayout()) {
                        $layouts = array_merge(
                            [sprintf('%s/%s.twig', $page->getSection(), $layout)],
                            $layouts
                        );
                    }
                }
                $layouts = array_merge(
                    ['page.html.twig'],
                    $layouts
                );
                if ($page->getLayout()) {
                    $layouts = array_merge(
                        [sprintf('%s.twig', $layout)],
                        $layouts
                    );
                }
        }

        return $layouts;
    }
}
