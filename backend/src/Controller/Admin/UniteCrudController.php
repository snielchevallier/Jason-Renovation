<?php

namespace App\Controller\Admin;

use App\Entity\Unite;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UniteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Unite::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Unite')
            ->setEntityLabelInPlural('Unites')
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code')
            ->setHelp('Code court affiche sur les devis, ex. m2, ml, u.');
        yield TextField::new('libelle');
        yield BooleanField::new('actif')
            ->setHelp('Une unite desactivee n\'est plus proposee sur les nouveaux devis.');
        yield IntegerField::new('position')
            ->setHelp('Ordre d\'affichage dans les listes.');
    }
}
