<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Participant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Conversation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Conversation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Conversation[]    findAll()
 * @method Conversation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }
    public function findConversationByParticipants($me, $otherUser){
        $qb = $this->createQueryBuilder('c');
        $qb
            ->select($qb->expr()->count('p.conversation'))
            ->join(Participant::class,'p','WITH','c.id=p.conversation') //c.participants
            ->where('p.user = :me')
            ->orWhere('p.user = :otherUser')
            ->setParameters([
                'me'=>$me,
                'otherUser'=>$otherUser
            ])
            ->groupBy('p.conversation');
        return $qb->getQuery()->getResult();
    }

    public function findConversationsByUser(int $userId)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->
        select('otherUser.username', 'c.id as conversationId', 'lm.content', 'lm.createdAt','meUser.id')
            ->innerJoin(Participant::class, 'p', 'WITH', $qb->expr()->neq('p.user', ':user'))
            ->innerJoin(Participant::class, 'me', 'WITH', $qb->expr()->eq('me.user', ':user'))
            ->leftJoin('c.lastMessage', 'lm')
            ->innerJoin('me.user', 'meUser')
            ->innerJoin('p.user', 'otherUser')
            ->where('meUser.id = :user')
            ->setParameter('user', $userId)
            ->orderBy('lm.createdAt', 'DESC')
        ;

        return $qb->getQuery()->getResult();
    }
    public function checkIfUserIsParticipant(int $conversationId, int $userId) {
        $qb = $this->createQueryBuilder('c');
        $qb->innerJoin(Participant::class,'p','WITH',"c.id=p.conversation")
            ->where('c.id = :conversationId')
            ->andWhere('p.user = :userId')
            ->setParameters([
                "conversationId"=>$conversationId,
                "userId"=>$userId
            ]);
        return $qb->getQuery()->getOneOrNullResult();
    }
    // /**
    //  * @return Conversation[] Returns an array of Conversation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
