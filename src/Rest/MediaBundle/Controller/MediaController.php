<?php

/*
 * This file is part of the KunstmaanBundlesCMS package.
 *
 * (c) Kunstmaan <https://github.com/Kunstmaan/KunstmaanBundlesCMS/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kunstmaan\Rest\MediaBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Hateoas\Representation\PaginatedRepresentation;
use Kunstmaan\MediaBundle\Entity\Folder;
use Kunstmaan\MediaBundle\Entity\Media;
use Kunstmaan\MediaBundle\Helper\MediaManager;
use Kunstmaan\MediaBundle\Repository\FolderRepository;
use Kunstmaan\MediaBundle\Repository\MediaRepository;
use Kunstmaan\Rest\CoreBundle\Controller\AbstractApiController;
use Kunstmaan\Rest\MediaBundle\Model\MediaModel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Swagger\Annotations as SWG;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Class MediaController
 *
 */
class MediaController extends AbstractApiController
{
    use ControllerTrait;

    /** @var MediaManager */
    private $mediaManager;

    /** @var Filesystem */
    private $fileSystem;

    /** @var EntityManagerInterface */
    private $doctrine;

    /**
     * MediaController constructor.
     * @param MediaManager $mediaManager
     * @param Filesystem   $fileSystem
     * @param EntityManagerInterface $doctrine
     */
    public function __construct(MediaManager $mediaManager, FileSystem $fileSystem, EntityManagerInterface $doctrine)
    {
        $this->mediaManager = $mediaManager;
        $this->fileSystem = $fileSystem;
        $this->doctrine = $doctrine;
    }

    /**
     * Retrieve media paginated
     *
     * @SWG\Get(
     *     path="/api/media",
     *     description="Get all media",
     *     operationId="getMedia",
     *     produces={"application/json"},
     *     tags={"media"},
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
     *         name="name",
     *         in="query",
     *         type="string",
     *         description="The name of the media",
     *         required=false,
     *     ),
     *     @SWG\Parameter(
     *         name="folderId",
     *         in="query",
     *         type="integer",
     *         description="The id of the folder to limit the search to",
     *         required=false,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returned when successful",
     *         @SWG\Schema(ref="#/definitions/MediaList")
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
     * @QueryParam(name="name", nullable=true, description="The internal name of the media", requirements="[\w\d_-]+", strict=true)
     * @QueryParam(name="page", nullable=false, default="1", requirements="\d+", description="The current page")
     * @QueryParam(name="limit", nullable=false, default="20", requirements="\d+", description="Amount of results")
     * @QueryParam(name="folderId", nullable=true, requirements="\d+", description="folder id", strict=true)
     *
     * @Rest\Get("/media")
     * @View(statusCode=200)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return PaginatedRepresentation
     */
    public function getAllMediaAction(ParamFetcherInterface $paramFetcher)
    {
        $page = $paramFetcher->get('page');
        $limit = $paramFetcher->get('limit');
        $name = $paramFetcher->get('name');
        $folderId = $paramFetcher->get('folderId');

        /** @var MediaRepository $repository */
        $repository = $this->doctrine->getRepository(Media::class);
        $qb = $repository->createQueryBuilder('n');
        $qb->where('n.deleted = 0');

        if ($folderId) {
            $qb
                ->andWhere('n.folder = :folder')
                ->setParameter('folder', $folderId);
        }
        if ($name) {
            $qb
                ->andWhere('n.name LIKE :name')
                ->setParameter('name', '%'.addcslashes($name, '%_').'%');
        }

        return $this->getPaginator()->getPaginatedQueryBuilderResult($qb, $page, $limit);
    }

    /**
     * Retrieve a single media
     *
     * @SWG\Get(
     *     path="/api/media/{id}",
     *     description="Get a media by ID",
     *     operationId="getMediaItem",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The media ID",
     *         required=true,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returned when successful",
     *         @SWG\Schema(ref="#/definitions/Media")
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
     * @Rest\Get("/media/{id}")
     * @View(statusCode=200)
     *
     * @param int $id
     * @return Media
     */
    public function getSingleMediaAction($id)
    {
        return $this->doctrine->getRepository('KunstmaanMediaBundle:Media')->find($id);
    }

    /**
     * Retrieve folders paginated
     *
     * @SWG\Get(
     *     path="/api/folder",
     *     description="Get all folder",
     *     operationId="getFolder",
     *     produces={"application/json"},
     *     tags={"media"},
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
     *         name="name",
     *         in="query",
     *         type="string",
     *         description="The name of the folder",
     *         required=false,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returned when successful",
     *         @SWG\Schema(ref="#/definitions/FolderList")
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
     * @QueryParam(name="name", nullable=true, description="The internal name of the folder", requirements="[\w\d_-]+", strict=true)
     * @QueryParam(name="page", nullable=false, default="1", requirements="\d+", description="The current page")
     * @QueryParam(name="limit", nullable=false, default="20", requirements="\d+", description="Amount of results")
     *
     * @Rest\Get("/folder")
     * @View(statusCode=200)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return PaginatedRepresentation
     */
    public function getAllFolderAction(ParamFetcherInterface $paramFetcher)
    {
        $page = $paramFetcher->get('page');
        $limit = $paramFetcher->get('limit');
        $name = $paramFetcher->get('name');

        /** @var MediaRepository $repository */
        $repository = $this->doctrine->getRepository(Folder::class);
        $qb = $repository->createQueryBuilder('n');
        $qb->where('n.deleted = 0');

        if ($name) {
            $qb
                ->andWhere('n.name LIKE :name')
                ->setParameter('name', '%'.addcslashes($name, '%_').'%');
        }

        return $this->getPaginator()->getPaginatedQueryBuilderResult($qb, $page, $limit);
    }

    /**
     * Creates a new Folder
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Post(
     *     path="/api/folder/{parentId}",
     *     description="Creates a Folder",
     *     operationId="postFolder",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="folder",
     *         in="body",
     *         type="object",
     *         @SWG\Schema(ref="#/definitions/PostFolder"),
     *     ),
     *     @SWG\Parameter(
     *         name="parentId",
     *         in="path",
     *         type="integer",
     *         description="The ID of the folder parent",
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
     * @ParamConverter(
     *     name="folder",
     *     converter="fos_rest.request_body",
     *     class="Kunstmaan\MediaBundle\Entity\Folder",
     *     options={
     *          "deserializationContext"={
     *              "groups"={
     *                  "Default"
     *              }
     *          },
     *          "validator"={
     *              "groups"={
     *                  "Default"
     *              }
     *          }
     *     }
     * )
     *
     * @Rest\Post("/folder/{parentId}", requirements={"parentId": "\d+"})
     *
     * @param Folder                           $folder
     * @param ConstraintViolationListInterface $validationErrors
     * @param int                              $parentId
     *
     * @return null
     * @throws \Exception
     */
    public function postFolderAction(Folder $folder, ConstraintViolationListInterface $validationErrors, $parentId = 0)
    {
        if (count($validationErrors) > 0) {
            return new \FOS\RestBundle\View\View($validationErrors, Response::HTTP_BAD_REQUEST);
        }

        $folderRepository = $this->doctrine->getRepository(Folder::class);
        if ($parentId) {
            /** @var Folder $parent */
            $parent = $folderRepository->find($parentId);
            $folder->setParent($parent);
        }

        $now = new \DateTime();
        $folder->setCreatedAt($now);
        $folder->setUpdatedAt($now);
        $folder->setDeleted(false);

        $folderRepository->save($folder);
    }

    /**
     * Creates a new Media
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Post(
     *     path="/api/media",
     *     description="Creates a Media",
     *     operationId="postMedia",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="media",
     *         in="body",
     *         type="object",
     *         @SWG\Schema(ref="#/definitions/UploadMedia"),
     *     ),
     *     @SWG\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         type="string",
     *         description="The authentication access token",
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
     * @ParamConverter(
     *     name="media",
     *     converter="fos_rest.request_body",
     *     class="Kunstmaan\Rest\MediaBundle\Model\MediaModel",
     *     options={
     *          "deserializationContext"={
     *              "groups"={
     *                  "Default",
     *                  "list"
     *              }
     *          },
     *          "validator"={
     *              "groups"={
     *                  "Default",
     *                  "list"
     *              }
     *          }
     *     }
     * )
     *
     * @Rest\Post("/media")
     *
     * @param MediaModel                       $media
     * @param ConstraintViolationListInterface $validationErrors
     *
     * @return null
     * @throws \Exception
     */
    public function postMediaAction(MediaModel $media, ConstraintViolationListInterface $validationErrors)
    {
        if (count($validationErrors) > 0) {
            return new \FOS\RestBundle\View\View($validationErrors, Response::HTTP_BAD_REQUEST);
        }

        $mediaRepository = $this->doctrine->getRepository(Media::class);
        $folderRepository = $this->doctrine->getRepository(Folder::class);
        $folderId = $media->getFolderId() ?? 1;
        /** @var Folder $folder */
        $folder = $folderRepository->find($folderId);

        $uploadedFile = $media->getUrl();
        if (!empty($media->getContent())) {
            $hashPath = '/tmp/'.uniqid('media', true);
            $this->fileSystem->mkdir($hashPath);
            $path = $hashPath.'/'.$media->getName();
            $this->fileSystem->touch($path);
            $this->fileSystem->appendToFile($path, base64_decode($media->getContent()));
            $uploadedFile = new UploadedFile($path, $media->getName());
        }

        $createdMedia = $this->mediaManager->createNew($uploadedFile);

        if (!$createdMedia) {
            return new \FOS\RestBundle\View\View(['error' => 'Could not create a file from the given content.'], Response::HTTP_BAD_REQUEST);
        }

        $createdMedia->setFolder($folder);
        $createdMedia->setName($media->getName());
        $createdMedia->setDescription($media->getDescription());
        $createdMedia->setCopyright($media->getCopyRight());
        $mediaRepository->save($createdMedia);
    }

    /**
     * updates a Media
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Put(
     *     path="/api/media/{id}",
     *     description="update a Media",
     *     operationId="updateMedia",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="media",
     *         in="body",
     *         type="object",
     *         @SWG\Schema(ref="#/definitions/putMedia"),
     *     ),
     *     @SWG\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         type="string",
     *         description="The authentication access token",
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
     * @ParamConverter(
     *     name="media",
     *     converter="fos_rest.request_body",
     *     class="Kunstmaan\Rest\MediaBundle\Model\MediaModel",
     *     options={
     *          "deserializationContext"={
     *              "groups"={
     *                  "update"
     *              }
     *          },
     *          "validator"={
     *              "groups"={
     *                  "update"
     *              }
     *          }
     *     }
     * )
     *
     * @Rest\Put("/media/{id}", requirements={"id": "\d+"})
     *
     * @param MediaModel                       $media
     * @param ConstraintViolationListInterface $validationErrors
     * @param int                              $id
     *
     * @return null
     * @throws \Exception
     */
    public function putMediaAction(MediaModel $media, ConstraintViolationListInterface $validationErrors, $id)
    {
        if (count($validationErrors) > 0) {
            return new \FOS\RestBundle\View\View($validationErrors, Response::HTTP_BAD_REQUEST);
        }

        $mediaRepository = $this->doctrine->getRepository(Media::class);
        /** @var Media $original */
        $original = $mediaRepository->find($id);
        if ($media->getFolderId()) {
            $folderId = $media->getFolderId();
            $folderRepository = $this->doctrine->getRepository(Folder::class);
            /** @var Folder $folder */
            $folder = $folderRepository->find($folderId);
            $original->setFolder($folder);
        }

        if ($media->getName()) {
            $original->setName($media->getName());
        }
        if ($media->getDescription()) {
            $original->setDescription($media->getDescription());
        }
        if ($media->getCopyRight()) {
            $original->setCopyright($media->getCopyRight());
        }

        $now = new \DateTime();
        $original->setUpdatedAt($now);
        $this->doctrine->getManager()->flush();
    }

    /**
     * updates Folder
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Put(
     *     path="/api/folder/{id}",
     *     description="updates a Folder",
     *     operationId="putFolder",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="folder",
     *         in="body",
     *         type="object",
     *         @SWG\Schema(ref="#/definitions/PostFolder"),
     *     ),
     *     @SWG\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         type="string",
     *         description="The authentication access token",
     *         required=true,
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The id of the folder",
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
     * @ParamConverter(
     *     name="folder",
     *     converter="fos_rest.request_body",
     *     class="Kunstmaan\MediaBundle\Entity\Folder",
     *     options={
     *          "deserializationContext"={
     *              "groups"={
     *                  "Default"
     *              }
     *          },
     *          "validator"={
     *              "groups"={
     *                  "Default"
     *              }
     *          }
     *     }
     * )
     *
     * @Rest\Put("/folder/{id}", requirements={"id": "\d+"})
     *
     * @param Folder                           $folder
     * @param ConstraintViolationListInterface $validationErrors
     * @param int                              $id
     *
     * @return null
     * @throws \Exception
     */
    public function putFolderAction(Folder $folder, ConstraintViolationListInterface $validationErrors, $id)
    {
        if (count($validationErrors) > 0) {
            return new \FOS\RestBundle\View\View($validationErrors, Response::HTTP_BAD_REQUEST);
        }

        $folderRepository = $this->doctrine->getRepository(Folder::class);
        /** @var Folder $original */
        $original = $folderRepository->find($id);

        $now = new \DateTime();
        $original->setUpdatedAt($now);
        $original->setDeleted(false);
        if ($folder->getName()) {
            $original->setName($folder->getName());
        }
        if ($folder->getInternalName()) {
            $original->setInternalName($folder->getInternalName());
        }
        if ($folder->getRel()) {
            $original->setRel($folder->getRel());
        }

        $this->doctrine->getManager()->flush();
    }

    /**
     * move Folder
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Put(
     *     path="/api/folder/{id}/{targetId}",
     *     description="moves a Folder",
     *     operationId="moveFolder",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         type="string",
     *         description="The authentication access token",
     *         required=true,
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The id of the folder",
     *         required=true,
     *     ),
     *     @SWG\Parameter(
     *         name="targetId",
     *         in="path",
     *         type="integer",
     *         description="The id of the target folder",
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
     * @Rest\Put("/folder/{id}/{targetId}", requirements={"id": "\d+", "targetId": "\d+"})
     *
     * @param int $id
     * @param int $targetId
     *
     * @return null
     * @throws \Exception
     */
    public function moveFolderAction($id, $targetId)
    {
        /** @var FolderRepository $folderRepository */
        $folderRepository = $this->doctrine->getRepository(Folder::class);
        /** @var Folder $original */
        $original = $folderRepository->find($id);
        /** @var Folder $target */
        $target = $folderRepository->find($targetId);

        /** @var ArrayCollection $children */
        $children = new ArrayCollection($folderRepository->getChildren($original));

        if (!$children->contains($target)) {
            $original->setParent($target);
            $target->addChild($original);
        } else {
            return new \FOS\RestBundle\View\View('Cannot move a folder into its own child.', Response::HTTP_BAD_REQUEST);
        }
        $this->doctrine->getManager()->flush();

        $folderRepository->recover();
    }

    /**
     * deletes Folder
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Delete(
     *     path="/api/folder/{id}",
     *     description="deletes a Folder",
     *     operationId="deleteFolder",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="X-Api-Key",
     *         in="header",
     *         type="string",
     *         description="The authentication access token",
     *         required=true,
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The id of the folder",
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
     * @Rest\Delete("/folder/{id}", requirements={"id": "\d+"})
     *
     * @param int $id
     *
     * @return null
     * @throws \Exception
     */
    public function deleteFolderAction($id)
    {
        $folderRepository = $this->doctrine->getRepository(Folder::class);
        /** @var Folder $original */
        $original = $folderRepository->find($id);
        $folderRepository->delete($original);
    }

    /**
     * deletes Media
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Delete(
     *     path="/api/media/{id}",
     *     description="deletes a media",
     *     operationId="deleteMedia",
     *     produces={"application/json"},
     *     tags={"media"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The id of the media",
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
     * @Rest\Delete("/media/{id}", requirements={"id": "\d+"})
     *
     * @param int $id
     *
     * @return null
     * @throws \Exception
     */
    public function deleteMediaAction($id)
    {
        /** @var MediaRepository $mediaRepository */
        $mediaRepository = $this->doctrine->getRepository(Media::class);
        /** @var Media $original */
        $original = $mediaRepository->find($id);
        $mediaRepository->delete($original);
    }
}
