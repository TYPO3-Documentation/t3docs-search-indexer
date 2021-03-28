<?php


namespace App\Twig;


use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var ParameterBagInterface */
    private $parameterBag;
    /** @var Environment */
    private $twigEnvironment;

    public function __construct(ParameterBagInterface $parameterBag, Environment $twigEnvironment)
    {
        $this->parameterBag = $parameterBag;
        $this->twigEnvironment = $twigEnvironment;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_assets', [$this, 'renderAssets']),
            new TwigFunction('render_single_asset', [$this, 'renderSingleAsset']),
            new TwigFunction('aggregationBucket', [$this, 'aggregationBucket'], ['is_safe' => ['html']]),
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

        return isset($assetsConfig[$assetType][$assetLocation]) ? $assetsConfig[$assetType][$assetLocation] : [];
    }


    public function aggregationBucket(string $category, string $index, array $bucket): string
    {
        $label = $bucket['key_as_string'] ?? $bucket['key'];
        $docCount = $bucket['doc_count'];
        $category = $this->fixWording($category);
        $key = $this->fixWording($bucket['key'], false);

        // check if checkbox has been set
        if (isset($_GET['filters'][$category][$key]) && $_GET['filters'][$category][$key] === 'true') {
            $checked = ' checked';
        } else {
            $checked = '';
        }
        $content = '<div class="custom-control custom-checkbox">'
            . '<input type="checkbox" class="custom-control-input" id="' . $category . '-' . $index . '" name="filters[' . $category . '][' . $key . ']" ' . $checked . ' value="true" onchange="this.form.submit()">'
            . '<label class="custom-control-label custom-control-label-hascount" for="' . $category . '-' . $index . '">'
            . '<span class="custom-control-label-title">' . $label . '</span> <span class="custom-control-label-count">(' . $docCount . ')</span>'
            . '</label>'
            . '</div>';

        return $content;
    }


    /**
     * @param      $in
     * @param bool $lowerCase
     *
     * @return mixed|string
     */
    private function fixWording($in, $lowerCase = true)
    {
        $in = str_replace(' ', '_', $in);
        if ($lowerCase) {
            $in = strtolower($in);
        }

        return $in;
    }

}
