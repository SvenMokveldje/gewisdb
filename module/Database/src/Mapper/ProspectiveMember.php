<?php

declare(strict_types=1);

namespace Database\Mapper;

use Database\Model\CheckoutSession as CheckoutSessionModel;
use Database\Model\Enums\CheckoutSessionStates;
use Database\Model\ProspectiveMember as ProspectiveMemberModel;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

use function count;
use function is_numeric;
use function strtolower;

class ProspectiveMember
{
    public function __construct(protected readonly EntityManager $em)
    {
    }

    /**
     * See if we can find a member with the same email.
     */
    public function hasMemberWith(string $email): bool
    {
        $qb = $this->em->createQueryBuilder();

        $qb->select('m')
            ->from(ProspectiveMemberModel::class, 'm')
            ->where('LOWER(m.email) = LOWER(:email)')
            ->setMaxResults(1);

        $qb->setParameter(':email', $email);

        $ret = $qb->getQuery()->getResult();

        return null !== $ret && count($ret) > 0;
    }

    /**
     * Search for a member.
     *
     * @return array<array-key, ProspectiveMemberModel>
     */
    public function search(
        string $query,
        string $type,
    ): array {
        $qb = $this->em->createQueryBuilder();

        $qb->select('m')
            ->from(ProspectiveMemberModel::class, 'm')
            ->where("CONCAT(LOWER(m.firstName), ' ', LOWER(m.lastName)) LIKE :name")
            ->orWhere("CONCAT(LOWER(m.firstName), ' ', LOWER(m.middleName), ' ', LOWER(m.lastName)) LIKE :name")
            ->orWhere('m.email LIKE :name')
            ->setMaxResults(128)
            ->orderBy('m.lidnr', 'DESC')
            ->setFirstResult(0);

        $qb->setParameter(':name', '%' . strtolower($query) . '%');

        // also allow searching for membership number
        if (is_numeric($query)) {
            $qb->orWhere('m.lidnr = :nr');
            $qb->orWhere('m.tueUsername = :nr');
            $qb->setParameter(':nr', $query);
        }

        // Get Checkout Session status.
        $qb->leftJoin(CheckoutSessionModel::class, 'cs', Join::WITH, 'cs.prospectiveMember = m.lidnr');
        $qbc = $this->em->createQueryBuilder();
        $qbc->select('MAX(css.id)')
            ->from(CheckoutSessionModel::class, 'css')
            ->where('css.prospectiveMember = m.lidnr');
        $qb->andWhere($qb->expr()->eq('cs.id', '(' . $qbc->getDQL() . ')'));

        if ('paid' === $type) {
            $qb->andWhere('cs.state = :paid')
                ->setParameter('paid', CheckoutSessionStates::Paid);
        } elseif ('failed' === $type) {
            $qb->andWhere('cs.state = :expired OR cs.state = :failed')
                ->setParameter('expired', CheckoutSessionStates::Expired)
                ->setParameter('failed', CheckoutSessionStates::Failed);
        } else {
            $qb->andWhere('cs.state = :created OR cs.state = :pending')
                ->setParameter('created', CheckoutSessionStates::Created)
                ->setParameter('pending', CheckoutSessionStates::Pending);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all members.
     *
     * @return array<array-key, ProspectiveMemberModel>
     */
    public function findAll(): array
    {
        return $this->getRepository()->findAll();
    }

    /**
     * Find a member (by lidnr).
     *
     * And calculate memberships.
     */
    public function find(int $lidnr): ?ProspectiveMemberModel
    {
        $qb = $this->em->createQueryBuilder();

        $qb->select('m, l')
            ->from(ProspectiveMemberModel::class, 'm')
            ->where('m.lidnr = :lidnr')
            ->leftJoin('m.lists', 'l');

        $qb->setParameter(':lidnr', $lidnr);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find all prospective members whose last Checkout Session has fully expired ((1 + 30) + 1 day ago) or failed 31
     * days ago.
     *
     * @return ProspectiveMemberModel[]
     */
    public function findWithFullyExpiredOrFailedCheckout(): array
    {
        $qb = $this->getRepository()->createQueryBuilder('m');
        $qb->leftJoin(CheckoutSessionModel::class, 'cs', Join::WITH, 'cs.prospectiveMember = m.lidnr');

        $qbc = $this->em->createQueryBuilder();
        $qbc->select('MAX(css.id)')
            ->from(CheckoutSessionModel::class, 'css')
            ->where('css.prospectiveMember = m.lidnr');
        $qb->where($qb->expr()->eq('cs.id', '(' . $qbc->getDQL() . ')'))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->andX(
                    $qb->expr()->eq('cs.state', ':expired'),
                    $qb->expr()->lt('cs.expiration', ':fullyExpired'),
                ),
                $qb->expr()->andX(
                    $qb->expr()->eq('cs.state', ':failed'),
                    $qb->expr()->lt('cs.expiration', ':fullyFailed'),
                ),
            ));

        $qb->setParameter('expired', CheckoutSessionStates::Expired)
            ->setParameter('failed', CheckoutSessionStates::Failed)
            ->setParameter('fullyExpired', (new DateTime())->sub(new DateInterval('P1D')))
            ->setParameter('fullyFailed', (new DateTime())->sub(new DateInterval('P31D')));

        return $qb->getQuery()->getResult();
    }

    /**
     * Persist a member model.
     */
    public function persist(ProspectiveMemberModel $member): void
    {
        $this->em->persist($member);
        $this->em->flush();
    }

    /**
     * Remove a member.
     */
    public function remove(ProspectiveMemberModel $member): void
    {
        $this->em->remove($member);
        $this->em->flush();
    }

    /**
     * Get the repository for this mapper.
     */
    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(ProspectiveMemberModel::class);
    }
}
