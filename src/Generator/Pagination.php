<?php
/*
 * Copyright (c) Arnaud Ligny <arnaud@ligny.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPoole\Generator;

use PHPoole\Page\Collection as PageCollection;
use PHPoole\Page\NodeType;
use PHPoole\Page\Page;

/**
 * Class Pagination.
 */
class Pagination implements GeneratorInterface
{
    /* @var \PHPoole\Config */
    protected $config;

    /**
     * {@inheritdoc}
     */
    public function __construct(\PHPoole\Config $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(PageCollection $pageCollection, \Closure $messageCallback)
    {
        $generatedPages = new PageCollection();

        $filteredPages = $pageCollection->filter(function (Page $page) {
            return in_array($page->getNodeType(), [NodeType::HOMEPAGE, NodeType::SECTION]);
        });

        /* @var $page Page */
        foreach ($filteredPages as $page) {
            if ($this->config->get('site.paginate.disabled')) {
                return $generatedPages;
            }

            $paginateMax = $this->config->get('site.paginate.max');
            $paginatePath = $this->config->get('site.paginate.path');
            $pages = $page->getVariable('pages');
            $path = $page->getPathname();

            // paginate
            if (is_int($paginateMax) && count($pages) > $paginateMax) {
                $paginateCount = ceil(count($pages) / $paginateMax);
                for ($i = 0; $i < $paginateCount; $i++) {
                    $pagesInPagination = array_slice($pages, ($i * $paginateMax), ($i * $paginateMax) + $paginateMax);
                    $alteredPage = clone $page;
                    // first page
                    if ($i == 0) {
                        $alteredPage
                            ->setId(Page::urlize(sprintf('%s/index', $path)))
                            ->setPathname(Page::urlize(sprintf('%s', $path)))
                            ->setVariable('aliases', [
                                sprintf('%s/%s/%s', $path, $paginatePath, 1),
                            ]);
                    } else {
                        $alteredPage
                            ->setId(Page::urlize(sprintf('%s/%s/%s/index', $path, $paginatePath, $i + 1)))
                            ->setPathname(Page::urlize(sprintf('%s/%s/%s', $path, $paginatePath, $i + 1)))
                            ->unVariable('menu');
                    }
                    // pagination
                    $pagination = ['pages' => $pagesInPagination];
                    if ($i > 0) {
                        $pagination += ['prev' => Page::urlize(sprintf('%s/%s/%s', $path, $paginatePath, $i))];
                    }
                    if ($i < $paginateCount - 1) {
                        $pagination += ['next' => Page::urlize(sprintf('%s/%s/%s', $path, $paginatePath, $i + 2))];
                    }
                    $alteredPage->setVariable('pagination', $pagination);

                    $generatedPages->add($alteredPage);
                }
            }
        }

        return $generatedPages;
    }
}
