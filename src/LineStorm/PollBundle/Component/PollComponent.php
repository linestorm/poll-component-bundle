<?php

namespace LineStorm\PollBundle\Component;

use LineStorm\BlogPostBundle\Model\Post;
use LineStorm\BlogPostBundle\Module\Component\AbstractBodyComponent;
use LineStorm\BlogPostBundle\Module\Component\ComponentInterface;
use LineStorm\PollBundle\Model\Poll;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;

class PollComponent extends AbstractBodyComponent implements ComponentInterface
{
    protected $name = 'Poll';
    protected $id = 'polls';

    public function isSupported($entity)
    {
        return ($entity instanceof Poll);
    }

    public function getForm(FormView $view)
    {
        return $this->container->get('templating')->render('LineStormPollBundle:Component:form.html.twig', array(
            'form'          => $view,
            'component'     => $this,
        ));
    }

    public function getFormAssetTemplate()
    {
        return 'LineStormPollBundle:Component:form-assets.html.twig';
    }

    /**
     * @param $entity Tag
     * @return string
     */
    public function getViewTemplate($entity)
    {
        return '';
    }

    public function getNewTemplate()
    {
        $tags = $this->modelManager->get('tag')->findBy(array(), array('name' => 'ASC'));

        return $this->templating->render('LineStormPollBundle:Component:new.html.twig', array(
            'tagEntities'   => null,
            'component'     => $this,
            'tags'          => $tags,
        ));
    }

    /**
     * @param $entity Tag
     * @return string
     */
    public function getEditTemplate($entity)
    {
        $tags = $this->modelManager->get('tag')->findBy(array(), array('name' => 'ASC'));

        return $this->templating->render('LineStormPollBundle:Component:new.html.twig', array(
            'tagEntities'   => $entity,
            'component'     => $this,
            'tags'          => $tags,
        ));
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('polls', 'collection', array(
                'type'      => 'linestorm_blog_form_post_poll',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label'     => false,
            ))
        ;
    }


    public function handleSave(Post $post, array $data)
    {
        $entities = array();

        foreach ($data as $eData) {
            $poll = $this->getEntityByName($eData);
            if (!($poll instanceof Poll)) {
                $poll = $this->createEntity($eData);
            }
            $post->addPoll($poll);
            $entities[] = $poll;
        }

        return $entities;
    }

    public function getEntityByName(array $data)
    {
        return $this->modelManager->get('poll')->findOneBy(array(
            'name' => $data['name']
        ));
    }

    public function createEntity(array $data)
    {
        $class  = $this->modelManager->getEntityClass('poll');
        $entity = new $class();

        $entity->setName($data['name']);

        return $entity;
    }

    public function getRoutes(LoaderInterface $loader)
    {
        return null;
    }
} 
