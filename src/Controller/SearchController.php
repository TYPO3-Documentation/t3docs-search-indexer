<?php

/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 16.01.18
 * Time: 09:07
 */

namespace App\Controller;

use App\Repository\ElasticRepository;
use App\Traits\TYPO3FluidTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller
{
    use TYPO3FluidTrait;

    /**
     * @Route("/", name="index")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(): Response
    {
        return $this->render('index');
    }

    /**
     * @Route("/search", name="searchresult")
     * @param Request $request
     * @return Response
     * @throws \Elastica\Exception\InvalidException
     */
    public function searchAction(Request $request): Response
    {
        $elasticRepository = new ElasticRepository();
        $query = $request->query->get('q');
        $this->view->assign('q', $query);
        $searchResults = $elasticRepository->findByQuery($query);
        $this->view->assign('results', $searchResults);
        return $this->render('search');
    }
}
