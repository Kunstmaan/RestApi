<?php

namespace Kunstmaan\Rest\FormBundle\Controller;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\ControllerTrait;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Hateoas\Representation\PaginatedRepresentation;
use Kunstmaan\FormBundle\Entity\AbstractFormPage;
use Kunstmaan\FormBundle\Entity\FormSubmission;
use Kunstmaan\FormBundle\Entity\FormSubmissionFieldTypes\StringFormSubmissionField;
use Kunstmaan\FormBundle\Entity\PageParts\AbstractFormPagePart;
use Kunstmaan\FormBundle\Event\FormEvents;
use Kunstmaan\FormBundle\Event\SubmissionEvent;
use Kunstmaan\FormBundle\Helper\FormMailerInterface;
use Kunstmaan\NodeBundle\Entity\NodeTranslation;
use Kunstmaan\Rest\CoreBundle\Controller\AbstractApiController;
use Mensura\CustomerZoneBundle\Model\Exception\ViolationException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Swagger\Annotations as SWG;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 */
class FormSubmissionController extends AbstractApiController
{
    use ControllerTrait;

    /** @var EntityManagerInterface */
    private $doctrine;

    /**
     * @var FormMailerInterface
     */
    private $formMailer;

    /** @var EventDispatcher */
    private $eventDispatcher;

    /** @var ValidatorInterface */
    private $validator;

    public function __construct(EntityManagerInterface $doctrine, FormMailerInterface $formMailer, EventDispatcher $eventDispatcher, ValidatorInterface $validator)
    {
        $this->doctrine = $doctrine;
        $this->formMailer = $formMailer;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
    }

    /**
     * Retrieve form submissions paginated
     *
     * @SWG\Get(
     *     path="/api/form-submission",
     *     description="Get all form-submissions",
     *     operationId="getFormSubmissions",
     *     produces={"application/json"},
     *     tags={"form-submission"},
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
     *         @SWG\Schema(ref="#/definitions/FormSubmissionList")
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
     * @Rest\Get("/form-submission")
     * @View(statusCode=200)
     *
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return PaginatedRepresentation
     */
    public function getAllFormSubmissionsAction(ParamFetcherInterface $paramFetcher)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $page = $paramFetcher->get('page');
        $limit = $paramFetcher->get('limit');

        /** @var ObjectRepository $repository */
        $repository = $this->doctrine->getRepository(FormSubmission::class);

        $result = $repository->findAll();

        return $this->getPaginator()->getPaginatedArrayResult($result, $page, $limit);
    }


    /**
     * Post a form submission
     *
     * @View(
     *     statusCode=202
     * )
     *
     * @SWG\Post(
     *     path="/api/pages/{id}/form-submission",
     *     description="post a form submission",
     *     operationId="postFormSubmission",
     *     produces={"application/json"},
     *     tags={"form-submission"},
     *     @SWG\Parameter(
     *         name="form-submission",
     *         in="body",
     *         type="object",
     *         @SWG\Schema(ref="#/definitions/PostFormSubmission"),
     *     ),
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         type="integer",
     *         description="The ID of the nodetranslation",
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
     *     name="formSubmission",
     *     converter="fos_rest.request_body",
     *     class="stdClass"
     * )
     *
     * @Rest\Post("/pages/{id}/form-submission", requirements={"id": "\d+"})
     *
     * @param \stdClass $formSubmission
     * @param int       $id
     *
     * @return null
     * @throws \Exception
     */
    public function postFormSubmissionAction(Request $request, \stdClass $formSubmission, int $id)
    {
        $createdFormSubmission = new FormSubmission();
        /** @var NodeTranslation $nodeTrans */
        $nodeTrans = $this->doctrine->find(NodeTranslation::class, $id);
        /** @var AbstractFormPage $page */
        $page = $nodeTrans->getRef($this->doctrine);
        $pageParts = $this->doctrine->getRepository('KunstmaanPagePartBundle:PagePartRef')->getPageParts($page, $page->getFormElementsContext());
        $formPageParts = [];
        $formFieldsCount = [];
        $formSubmissionFields = [];
        $validationErrors = new ConstraintViolationList();

        foreach ($pageParts as $pagePart) {
            if ($pagePart instanceof AbstractFormPagePart) {
                if (isset($formPageParts[get_class($pagePart)])) {
                    $formPageParts[get_class($pagePart)][] = $pagePart;
                    ++$formFieldsCount[get_class($pagePart)];
                } else {
                    $formPageParts[get_class($pagePart)] = [$pagePart];
                    $formFieldsCount[get_class($pagePart)] = 1;
                }
            }
        }

        $createdFormSubmission->setIpAddress($request->getClientIp());
        $createdFormSubmission->setNode($nodeTrans->getNode());
        $createdFormSubmission->setLang($nodeTrans->getLang());
        /**
         * @var string $class
         * @var \stdClass $field
         */
        foreach ((array)$formSubmission as $class => $field) {
            if(!isset($field->position, $field->value)) {
                throw new \Exception("every submitted form field needs a value and position");
            }
            $index = $field->position;
            $value = $field->value;
            /** @var AbstractFormPagePart $pagePart */
            $pagePart = $formPageParts[$class][$index];
            $constraints = $pagePart->getConstraints();
            /** @var Constraint $constraint */
            $validationErrors->addAll($this->validator->validate($value, $constraints));
            --$formFieldsCount[$class];
            $submissionField = new StringFormSubmissionField();
            $submissionField->setSubmission($createdFormSubmission);
            $formSubmissionFields[] = $submissionField;
        }

        if($validationErrors->count()) {
            throw new ViolationException($validationErrors, "validation failed");
        }

        foreach ($formFieldsCount as $count) {
            if($count !== 0) {
                throw new \Exception("missing some pieces of the form");
            }
        }

        $this->doctrine->persist($createdFormSubmission);
        foreach($formSubmissionFields as $submissionField) {
            $this->doctrine->persist($submissionField);
        }

        $this->doctrine->flush();
        $this->doctrine->refresh($createdFormSubmission);

        $event = new SubmissionEvent($createdFormSubmission, $page);
        $this->eventDispatcher->dispatch(FormEvents::ADD_SUBMISSION, $event);

        $from = $page->getFromEmail();
        $to = $page->getToEmail();
        $subject = $page->getSubject();
        if (!empty($from) && !empty($to) && !empty($subject)) {
            $this->formMailer->sendContactMail($createdFormSubmission, $from, $to, $subject);
        }
    }
}