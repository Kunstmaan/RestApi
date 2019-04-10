<?php

namespace Mensura\CustomerZoneBundle\Model\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Class ValidationException.
 */
class ViolationException extends \Exception
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * ValidationHttpException constructor.
     *
     * @param ConstraintViolationListInterface $validationErrors
     * @param null                             $message
     * @param \Exception|null                  $previous
     * @param int                              $code
     */
    public function __construct(ConstraintViolationListInterface $validationErrors, $message = null, \Exception $previous = null)
    {
        parent::__construct($message, Response::HTTP_BAD_REQUEST, $previous);
        $this->setConstraintViolationList($validationErrors);
    }

    /**
     * @param ConstraintViolationListInterface $validationErrors
     */
    public function setConstraintViolationList(ConstraintViolationListInterface $validationErrors)
    {
        /** @var ConstraintViolation $item */
        foreach ($validationErrors as $item) {
            $this->data[] = [
                'property_path' => $item->getPropertyPath(),
                'message' => $item->getMessage(),
                'variables' => $item->getParameters()
            ];
        }
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}