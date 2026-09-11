<?php

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Entreprise est un singleton (une seule ligne, creee par migration) :
 * ni creation, ni suppression, ni liste — seule l'edition est accessible
 * (le menu pointe directement dessus, voir DashboardController).
 */
class EntrepriseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Entreprise::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Entreprise')
            ->setEntityLabelInPlural('Entreprise');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::INDEX);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Identite');
        yield TextField::new('nom');
        yield TextField::new('siret')->setHelp('14 chiffres');
        yield TextField::new('numeroTvaIntracom', 'N° TVA intracommunautaire');

        yield FormField::addFieldset('Adresse');
        yield TextField::new('adresse.ligne1', 'Adresse (ligne 1)');
        yield TextField::new('adresse.ligne2', 'Complement');
        yield TextField::new('adresse.codePostal', 'Code postal');
        yield TextField::new('adresse.ville', 'Ville');
        yield TextField::new('adresse.pays', 'Pays');

        yield FormField::addFieldset('Contact');
        yield TelephoneField::new('telephone');
        yield EmailField::new('email');

        yield FormField::addFieldset('Devis');
        yield IntegerField::new('delaiValiditeDevisJours', 'Delai de validite des devis (jours)');
        yield TextField::new('logo')->setHelp('Chemin ou URL du logo (upload reel a venir)');
        yield TextareaField::new('mentionsLegales', 'Mentions legales')->setNumOfRows(6);
        yield TextareaField::new('conditionsPaiement', 'Conditions de paiement')->setNumOfRows(4);
    }
}
