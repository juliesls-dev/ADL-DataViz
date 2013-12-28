<?php

namespace Enstb\Bundle\VisplatBundle\Administrator;

use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;

class UsersAdmin extends Admin
{
    // Fields to be shown on create/edit forms
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('Name', 'text', array('label' => 'Name'))
            ->add('LastName', 'text', array('label' => 'Last name'))
            ->add('Email') //if no type is specified, SonataAdminBundle tries to guess it
            ->add('Username')
            ->add('Password')
            ->add('Roles','sonata_type_model',array('expanded' => true, 'compound' => true, 'multiple' => true))
    ;
    }

    // Fields to be shown on filter forms
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('Name')
            ->add('LastName')
            ->add('Roles')
        ;
    }

    // Fields to be shown on lists
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('Name')
            ->add('LastName')
            ->add('Email')
            ->add('Roles','sonata_type_model')
        ;
    }
}