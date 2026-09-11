<?php

namespace App\Controller\Admin;

use App\Entity\Tva;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TvaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tva::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Taux de TVA')
            ->setEntityLabelInPlural('Taux de TVA')
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield NumberField::new('taux')
            ->setNumDecimals(2)
            ->setHelp('En pourcentage, ex. 20.00');
        yield TextField::new('libelle');
        yield BooleanField::new('actif')
            ->setHelp('Un taux desactive n\'est plus propose sur les nouveaux devis, mais reste lisible sur les devis existants.');
        yield IntegerField::new('position')
            ->setHelp('Ordre d\'affichage dans les listes.');
    }
}
