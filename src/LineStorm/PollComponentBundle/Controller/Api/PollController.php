<?php

namespace LineStorm\PollComponentBundle\Controller\Api;

use Doctrine\ORM\Query;
use FOS\RestBundle\Routing\ClassResourceInterface;
use LineStorm\CmsBundle\Controller\Api\AbstractApiController;
use LineStorm\PollComponentBundle\Model\Poll;
use LineStorm\PollComponentBundle\Model\PollAnswer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class PollController
 *
 * @package LineStorm\PollComponentBundle\Controller\Api
 * @author  Andy Thorne <contrabandvr@gmail.com>
 */
class PollController extends AbstractApiController implements ClassResourceInterface
{

    /**
     * Get a list of all consumables
     *
     * [GET] /api/cms/polls.{_format}
     */
    public function cgetAction()
    {
        $modelManager = $this->getModelManager();

        $em = $modelManager->getManager();

        $now = new \DateTime();

        $dql = "
            SELECT
                p,o
            FROM
                {$modelManager->getEntityClass('poll')} p
                JOIN p.options o
            WHERE
              p.startDate <= :date
              AND (p.endDate >= :date OR p.endDate IS NULL)
        ";
        $polls = $em->createQuery($dql)->setParameters(array(
            'date' => $now,
        ))->getArrayResult();

        $view = $this->createResponse($polls);

        return $this->get('fos_rest.view_handler')->handle($view);
    }

    public function getResultsAction($id)
    {
        $modelManager = $this->getModelManager();
        $poll = $modelManager->get('poll')->findResults($id);

        $view = $this->createResponse($poll);

        return $this->get('fos_rest.view_handler')->handle($view);
    }


    public function voteAction($id)
    {
        $modelManager = $this->getModelManager();
        $em = $modelManager->getManager();

        $dql = "
            SELECT
                p,o
            FROM
                {$modelManager->getEntityClass('poll')} p
                JOIN p.options o
            WHERE
                p.id = ?1
                  AND p.startDate <= :date
                  AND (p.endDate >= :date OR p.endDate IS NULL)
        ";
        $poll = $em->createQuery($dql)->setParameters(array(
            1      => $id,
            'date' => new \DateTime(),
        ))->getSingleResult();

        try
        {
            if($poll instanceof Poll)
            {
                if(!$poll->getAllowAnonymous() && !$this->getUser())
                {
                    throw new AccessDeniedException("You must be logged in to vote on this poll");
                }

                $ip = $this->getRequest()->getClientIp();
                $user = $this->getUser();

                // validate that this user has not already voted!
                $aClass = $modelManager->getEntityClass('poll_answer');
                $dql = "
                  SELECT
                    a
                  FROM
                    {$aClass} a
                    JOIN a.option o
                  WHERE
                    a.user = :user
                    AND a.ip = :ip
                    AND o.poll = :poll
                ";
                $answerQuery = $em->createQuery($dql)->setParameters(array(
                    'user' => $user,
                    'ip'   => $ip,
                    'poll' => $poll,
                ));
                $previousAnswers = $answerQuery->getResult();

                if($previousAnswers)
                {
                    throw new AccessDeniedException("You cannot vote twice");
                }

                $payload = json_decode($this->getRequest()->getContent(), true);

                $baseKey = "poll_{$poll->getId()}_option";
                $optionCount = 0;

                foreach($poll->getOptions() as $option)
                {
                    $key = ($poll->getMultiple()) ? $baseKey.'_'.$option->getId() : $baseKey;

                    if(array_key_exists($key, $payload))
                    {
                        $optionKeys = $payload[$key];

                        if(is_array($optionKeys) && in_array($option->getId(), $optionKeys) || is_numeric($optionKeys) && $optionKeys == $option->getId())
                        {
                            /** @var PollAnswer $answer */
                            $class = $modelManager->getEntityClass('poll_answer');
                            $answer = new $class;
                            $answer->setAnsweredOn(new \DateTime());
                            $answer->setOption($option);
                            $answer->setIp($ip);

                            if($user)
                                $answer->setUser($user);

                            $em->persist($answer);

                            ++$optionCount;
                        }

                    }
                }

                if(!$optionCount)
                {
                    throw new BadRequestHttpException("You must select an option");
                }

                $em->flush();

                $view = $this->createResponse(array(
                    'results' => $this->generateUrl('linestorm_cms_module_post_api_get_poll_results', array('id' => $poll->getId()))
                ));
            }
            else
            {
                throw new NotFoundHttpException("Poll not Found");
            }
        }
        catch(AccessDeniedException $e)
        {
            $view = $this->createResponse(array(
                'error' => $e->getMessage(),
            ), 403);
        }

        return $this->get('fos_rest.view_handler')->handle($view);
    }

    /**
     * Get a post
     *
     * [GET] /api/cms/poll/{id}.{_format}
     */
    public function getAction($id)
    {
        $modelManager = $this->getModelManager();

        $em = $modelManager->getManager();

        $dql = "
            SELECT
                p,o
            FROM
                {$modelManager->getEntityClass('poll')} p
                JOIN p.options o
            WHERE
                p.id = ?1
                AND p.startDate <= :date
                AND (p.endDate >= :date OR p.endDate IS NULL)
        ";
        $polls = $em->createQuery($dql)->setParameters(array(
            1      => $id,
            'date' => new \DateTime(),
        ))->getSingleResult(Query::HYDRATE_ARRAY);

        $view = $this->createResponse($polls);

        return $this->get('fos_rest.view_handler')->handle($view);
    }

}
