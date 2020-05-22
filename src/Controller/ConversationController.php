<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Participant;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\WebLink\Link;

/**
 * @Route("/conversations", name="conversations")
 */
class ConversationController extends AbstractController
{
    public $userRepository;
    public $em;
    public $conversationRepository;
    public function __construct(UserRepository $userRepository, EntityManagerInterface $em, ConversationRepository $conversationRepository)
    {
        $this->userRepository = $userRepository;
        $this->em = $em;
        $this->conversationRepository = $conversationRepository;
    }

    /**
     * @Route("/", name="newConversation")
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $otherUser = $request->get('otherUser',0);
        $otherUser = $this->userRepository->find($otherUser);
        if(is_null($otherUser))
        {
            throw new \Exception("The user was not found");
        }
        if($otherUser->getId() === $this->getUser()->getId())
        {
            throw new \Exception("You cannot create a conv with urself");
        }
        $conversation=$this->findConversationByParticipants(
            $otherUser->getId(),
            $this->getUser()->getId()
        );
        //dd($otherUser);
        //dd($this->getUser());
        //dd($conversation);
        if(count($conversation))
        {
            throw new \Exception("Conv exists");
        }
        $conversation = new Conversation();
        $participant = new Participant();
        $participant ->setUser($this->getUser());
        $participant ->setConversation($conversation);

        $otherParticipant = new Participant();
        $otherParticipant ->setUser($otherUser);
        $otherParticipant ->setConversation($conversation);

        $this->em->getConnection()->beginTransaction();
        try{
            $this->em->persist($conversation);
            $this->em->persist($participant);
            $this->em->persist($otherParticipant);
            $this->em->flush();
            $this->em->commit();
        } catch(\Exception $e)
        {
            $this->em->rollback();
            throw $e;
        }
        return $this->json([
            'id'=>$conversation->getId()
        ],Response::HTTP_CREATED,[],[]);
    }

    private function findConversationByParticipants(int $otherUserId, int $myId)
    {
       return $this->conversationRepository->findConversationByParticipants($myId, $otherUserId);
    }

    /**
     * @Route("/getConvs", name="getConversations")
     * @param Request $request
     * @return JsonResponse
     */
    public function getConvs(Request $request) {
        $conversations = $this->conversationRepository->findConversationsByUser($this->getUser()->getId());
        $hubUrl = $this->getParameter('mercure.default_hub');
        $this->addLink($request, new Link($hubUrl));
        return $this->json($conversations);
    }
}
