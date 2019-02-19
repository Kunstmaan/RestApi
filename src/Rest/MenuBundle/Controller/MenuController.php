<?php

namespace Kunstmaan\Rest\MenuBundle\Controller;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\UserBundle\Doctrine\UserManager;
use Hateoas\Representation\PaginatedRepresentation;
use Kunstmaan\FormBundle\Entity\FormSubmission;
use Kunstmaan\MenuBundle\Entity\Menu;
use Kunstmaan\MenuBundle\Entity\MenuItem;
use Kunstmaan\Rest\CoreBundle\Controller\AbstractApiController;
use Swagger\Annotations as SWG;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 */
class MenuController extends AbstractApiController
{
    use ControllerTrait;

    /** @var Registry */
    private $doctrine;

    /**
     * MediaController constructor.
     * @param Registry     $doctrine
     * @param TokenStorage $tokenStorage
     * @param UserManager  $userManager
     */
    public function __construct(Registry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * Retrieve menu paginated
     *
     * @SWG\Get(
     *     path="/api/menu",
     *     description="Get all menu",
     *     operationId="getMenus",
     *     produces={"application/json"},
     *     tags={"menu"},
     *     @SWG\Parameter(
     *         name="page",
     *         in="query",
     *         type="integer",
     *         description="The current page",
     *         required=false,
     *     ),
     *     @SWG\Parameter(
     *         name="limit",
     *         in="query",
     *         type="integer",
     *         description="Amount of results (default 20)",
     *         required=false,
     *     ),
     *     @SWG\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         type="string",
     *         description="The authentication access token",
     *         required=true,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returned when successful",
     *         @SWG\Schema(ref="#/definitions/MenuList")
     *     ),
     *     @SWG\Response(
     *         response=403,
     *         description="Returned when the user is not authorized to fetch media",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     )
     * )
     *
     * @QueryParam(name="page", nullable=false, default="1", requirements="\d+", description="The current page")
     * @QueryParam(name="limit", nullable=false, default="20", requirements="\d+", description="Amount of results")
     *
     * @Rest\Get("/menu")
     * @View(statusCode=200)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return PaginatedRepresentation
     */
    public function getAllMenuAction(ParamFetcherInterface $paramFetcher)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $page = $paramFetcher->get('page');
        $limit = $paramFetcher->get('limit');

        /** @var ObjectRepository $repository */
        $repository = $this->doctrine->getRepository(Menu::class);

        $result = $repository->findAll();

        return $this->getPaginator()->getPaginatedArrayResult($result, $page, $limit);
    }

    /**
     * Retrieve menu items paginated
     *
     * @SWG\Get(
     *     path="/api/menu/{id}/items",
     *     description="Get all menu items for menu",
     *     operationId="getMenuItems",
     *     produces={"application/json"},
     *     tags={"menu"},
     *     @SWG\Parameter(
     *         name="page",
     *         in="query",
     *         type="integer",
     *         description="The current page",
     *         required=false,
     *     ),
     *     @SWG\Parameter(
     *         name="limit",
     *         in="query",
     *         type="integer",
     *         description="Amount of results (default 20)",
     *         required=false,
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The id of the menu",
     *         required=true,
     *     ),
     *     @SWG\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         type="string",
     *         description="The authentication access token",
     *         required=true,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returned when successful",
     *         @SWG\Schema(ref="#/definitions/MenuItemList")
     *     ),
     *     @SWG\Response(
     *         response=403,
     *         description="Returned when the user is not authorized to fetch media",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     )
     * )
     *
     * @QueryParam(name="page", nullable=false, default="1", requirements="\d+", description="The current page")
     * @QueryParam(name="limit", nullable=false, default="20", requirements="\d+", description="Amount of results")
     *
     * @Rest\Get("/menu/{id}/items")
     * @View(statusCode=200)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return PaginatedRepresentation
     */
    public function getAllMenuItemAction(ParamFetcherInterface $paramFetcher, int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $page = $paramFetcher->get('page');
        $limit = $paramFetcher->get('limit');

        /** @var ObjectRepository $repository */
        $repository = $this->doctrine->getRepository(Menu::class);

        /** @var Menu $result */
        $result = $repository->find($id);
        $result = $result->getItems();

        return $this->getPaginator()->getPaginatedArrayResult($result->toArray(), $page, $limit);
    }

    /**
     * deletes MenuItem
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Delete(
     *     path="/api/menu-item/{id}",
     *     description="deletes a menu item",
     *     operationId="deleteMenuItem",
     *     produces={"application/json"},
     *     tags={"menu"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The id of the menu item",
     *         required=true,
     *     ),
     *     @SWG\Response(
     *         response=202,
     *         description="Returned when successful",
     *     ),
     *     @SWG\Response(
     *         response=403,
     *         description="Returned when the user is not authorized",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     )
     * )
     *
     * @Rest\Delete("/menu-item/{id}", requirements={"id": "\d+"})
     *
     * @param int $id
     *
     * @return null
     * @throws \Exception
     */
    public function deleteMenuItemAction(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var ObjectRepository $repository */
        $repository = $this->doctrine->getRepository(MenuItem::class);
        $item = $repository->find($id);
        $this->doctrine->getManager()->remove($item);
        $this->doctrine->getManager()->flush();
    }

    /**
     * deletes Menu
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Delete(
     *     path="/api/menu/{id}",
     *     description="deletes a menu",
     *     operationId="deleteMenu",
     *     produces={"application/json"},
     *     tags={"menu"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The id of the menu",
     *         required=true,
     *     ),
     *     @SWG\Response(
     *         response=202,
     *         description="Returned when successful",
     *     ),
     *     @SWG\Response(
     *         response=403,
     *         description="Returned when the user is not authorized",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     ),
     *     @SWG\Response(
     *         response="default",
     *         description="unexpected error",
     *         @SWG\Schema(ref="#/definitions/ErrorModel")
     *     )
     * )
     *
     * @Rest\Delete("/menu/{id}", requirements={"id": "\d+"})
     *
     * @param int $id
     *
     * @return null
     * @throws \Exception
     */
    public function deleteMenuAction(int $id)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var ObjectRepository $repository */
        $repository = $this->doctrine->getRepository(Menu::class);
        /** @var Menu $menu */
        $menu = $repository->find($id);
        foreach($menu->getItems() as $item) {
            $this->doctrine->getManager()->remove($item);
        }
        $this->doctrine->getManager()->remove($menu);
        $this->doctrine->getManager()->flush();
    }
}