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

}