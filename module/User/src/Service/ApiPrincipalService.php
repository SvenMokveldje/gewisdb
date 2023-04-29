<?php

declare(strict_types=1);

namespace User\Service;

use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use User\Form\ApiPrincipal as ApiPrincipalForm;
use User\Mapper\ApiPrincipalMapper;
use User\Model\ApiPrincipal as ApiPrincipalModel;
use User\Model\Enums\ApiPermissions;

use function array_map;

class ApiPrincipalService
{
    public function __construct(
        protected readonly ApiPrincipalMapper $mapper,
        protected readonly ApiPrincipalForm $apiPrincipalForm,
    ) {
    }

    public function getCreateForm(): ApiPrincipalForm
    {
        return $this->apiPrincipalForm;
    }

    public function getEditForm(ApiPrincipalModel $principal): ApiPrincipalForm
    {
        $this->apiPrincipalForm->bind($principal);

        return $this->apiPrincipalForm;
    }

    /**
     * @param array<array-key,mixed> $data
     */
    public function create(
        array $data,
        ?FlashMessenger $flashMessenger,
    ): bool {
        $form = $this->getCreateForm();
        $form->setData($data);

        if (!$form->isValid()) {
            return false;
        }

        $data = $form->getData();

        $principal = new ApiPrincipalModel();
        $principal->setDescription($data['description']);
        $token = $principal->generateToken();

        $permissions = array_map(
            static function ($p): ApiPermissions {
                return ApiPermissions::from($p);
            },
            $data['permissions'],
        );
        $principal->setPermissions($permissions);

        $this->mapper->persist($principal);

        $flashMessenger?->addInfoMessage(
            'Your API token is "' . $token . '". This value will NOT be shown again',
        );

        return true;
    }

    /**
     * @param array<array-key,mixed> $data
     */
    public function edit(
        ApiPrincipalModel $principal,
        array $data,
    ): bool {
        $form = $this->getEditForm($principal);

        $form->setData($data);

        if (!$form->isValid()) {
            return false;
        }

        $this->mapper->persist($principal);

        return true;
    }

    /**
     * Get all Api Principals.
     *
     * @return ApiPrincipalModel[]
     */
    public function findAll(): array
    {
        return $this->mapper->findAll();
    }

    /**
     * Get an API principal by ID
     */
    public function find(int $id): ?ApiPrincipalModel
    {
        return $this->mapper->find($id);
    }
}
