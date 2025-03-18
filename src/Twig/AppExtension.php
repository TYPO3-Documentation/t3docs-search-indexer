<?php

namespace App\Twig;

use App\Config\Labels;
use App\Dto\SearchDemand;
use App\Helper\SlugBuilder;
use App\Helper\VersionFilter;
use App\Helper\VersionSorter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Environment $twigEnvironment,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_assets', $this->renderAssets(...)),
            new TwigFunction('render_single_asset', $this->renderSingleAsset(...)),
            new TwigFunction('aggregationBucket', $this->aggregationBucket(...), ['is_safe' => ['html']]),
            new TwigFunction('generateLinkWithout', $this->generateLinkWithout(...)),
            new TwigFunction('generateLinkWith', $this->generateLinkWith(...)),
            new TwigFunction('getReportIssueLink', $this->getReportIssueLink(...)),
            new TwigFunction('getHowToEditLink', $this->getHowToEditLink(...)),
            new TwigFunction('getEditOnGitHubLink', $this->getEditOnGitHubLink(...)),
            new TwigFunction('getLabelForFilter', $this->getLabelForFilter(...)),
            new TwigFunction('sortVersions', VersionSorter::sortVersions(...)),
            new TwigFunction('filterVersions', VersionFilter::filterVersions(...)),
            new TwigFunction('buildSlug', SlugBuilder::build(...)),
        ];
    }

    public function renderAssets(string $assetType, string $assetLocation = 'header'): string
    {
        $assets = $this->getAssetByTypeAndLocation($assetType, $assetLocation);

        return $this->twigEnvironment->render('extension/assets.html.twig', [
            'assets' => $assets,
            'assetType' => $assetType,
        ]);
    }

    public function renderSingleAsset(string $assetUrl, string $assetType): string
    {
        $isUrlExternal = filter_var($assetUrl, FILTER_VALIDATE_URL);
        $isLocalAsset = $isUrlExternal ? true : false;

        return $this->twigEnvironment->render('extension/single_assert.html.twig', [
            'assetUrl' => $assetUrl,
            'assetType' => $assetType,
            'isLocalAsset' => $isLocalAsset,
        ]);
    }

    private function getAssetByTypeAndLocation(string $assetType, string $assetLocation): array
    {
        $assetsConfig = $this->parameterBag->get('assets');

        return $assetsConfig[$assetType][$assetLocation] ?? [];
    }

    public function aggregationBucket(string $category, string $index, array $bucket, array $searchFilter): string
    {
        $category = strtolower($category);
        $label = $bucket['key_as_string'] ?? $bucket['key'];
        $docCount = $bucket['doc_count'];
        $key = $bucket['key'];

        // check if checkbox has been set
        if (isset($searchFilter[$category][$key]) && $searchFilter[$category][$key] === 'true') {
            $checked = ' checked';
        } else {
            $checked = '';
        }

        return '<div class="form-check">'
            . '<input type="checkbox" class="form-check-input" id="' . $category . '-' . $index . '" name="filters[' . $category . '][' . $key . ']" ' . $checked . ' value="true" onchange="this.form.submit()">'
            . '<label class="form-check-label custom-control-label-hascount" for="' . $category . '-' . $index . '">'
            . '<span class="custom-control-label-title">' . $label . '</span> <span class="custom-control-label-count">(' . $docCount . ')</span>'
            . '</label>'
            . '</div>';
    }

    public function getEditOnGitHubLink(): string
    {
        return 'https://github.com/TYPO3-Documentation/t3docs-search-indexer';
    }

    public function getHowToEditLink(): string
    {
        return 'https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/Howto/EditOnGithub.html';
    }

    public function getReportIssueLink(): string
    {
        return 'https://github.com/TYPO3-Documentation/t3docs-search-indexer/issues/new';
    }

    /**
     * @param      $in
     *
     * @return array|string|string[]
     */
    private function fixWording($in, bool $lowerCase = true)
    {
        $in = str_replace(' ', '_', (string)$in);
        if ($lowerCase) {
            $in = strtolower($in);
        }

        return $in;
    }

    /**
     * Generates a frontend link to be used in Twig with the current demand filters, excluding a specific value
     * in the specified filter
     *
     * Example:
     *  Suppose we have the following query parameters:
     *   - filters[vendor]=typo3
     *   - filters[sversion]=main
     *
     *  If we want to generate a link that excludes vendor=typo3, we can use this method by passing:
     *   - $key = vendor
     *   - $value = typo3
     *
     * This will generate a link that includes only filters[sversion]=main.
     *
     * @param SearchDemand $demand current search demand object
     * @param string $key name of the filter (e.g. vendor in filters[vendor]=typo3)
     * @param mixed $value value of the filter (e.g. typo3 in filters[vendor]=typo3)
     * @param bool $removeQuery whether to keep or remove 'q' from query parameters when generating the link
     * @return string generated link without specific query param if exists
     */
    private function generateLinkWithout(SearchDemand $demand, string $key, mixed $value, bool $removeQuery = true): string
    {
        $filters['filters'] = $demand->withFilterValueForLinkGeneration($key, $value);

        if ($removeQuery === false) {
            $filters['q'] = $demand->getQuery();
        }

        return $this->urlGenerator->generate('search-with-suggest', $filters);
    }

    /**
     * Generates a frontend link to be used in Twig with the current demand filters, including a specific value
     * in the specified filter
     *
     * Example:
     *  Suppose we have the following query parameter:
     *   - filters[sversion]=main
     *
     *  If we want to generate a link that includes vendor = typo3, we can use this method by passing:
     *   - $key = vendor
     *   - $value = typo3
     *
     * This will generate a link that includes both filters[sversion]=main and filters[vendor]=typo3
     *
     * @param SearchDemand $demand current search demand object
     * @param string $key name of the filter (e.g. vendor in filters[vendor]=typo3)
     * @param mixed $value value of the filter (e.g. typo3 to add to the query string)
     * @param bool $addQuery whether to include or exclude 'q' from query parameters when generating the link
     * @return string generates a link with a specific query parameter. If an invalid filter is provided, the new filters[$key]=$value will be ignored
     */
    private function generateLinkWith(SearchDemand $demand, string $key, mixed $value, bool $removeQuery = true): string
    {
        $filters['filters'] = $demand->withoutFilterValueForLinkGeneration($key, $value);

        if ($removeQuery === false) {
            $filters['q'] = $demand->getQuery();
        }

        return $this->urlGenerator->generate('search-with-suggest', $filters);
    }

    private function getLabelForFilter(string $filter): string
    {
        return Labels::getLabelForEsColumn($filter);
    }
}
