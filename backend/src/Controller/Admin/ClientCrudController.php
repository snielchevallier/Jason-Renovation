<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Meme perimetre fonctionnel que /api/clients (cree/edite/supprime) : le
 * back-office est un client de plus de l'API metier, pas juste des donnees
 * de reference.
 */
class ClientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Client::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Client')
            ->setEntityLabelInPlural('Clients')
            ->setDefaultSort(['nom' => 'ASC'])
            ->setSearchFields(['nom', 'email', 'telephone']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Identite');
        yield TextField::new('nom');
        yield EmailField::new('email')->hideOnIndex();
        yield TelephoneField::new('telephone')->hideOnIndex();

        yield FormField::addFieldset('Adresse');
        yield TextField::new('adresse.ligne1', 'Adresse (ligne 1)')->onlyOnForms();
        yield TextField::new('adresse.ligne2', 'Complement')->onlyOnForms();
        yield TextField::new('adresse.codePostal', 'Code postal')->onlyOnForms();
        yield TextField::new('adresse.ville', 'Ville')->onlyOnForms();
        yield TextField::new('adresse.pays', 'Pays')->onlyOnForms();

        yield FormField::addFieldset('Notes');
        yield TextareaField::new('notes')->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Cree le')->hideOnForm()->onlyOnDetail();
        yield DateTimeField::new('updatedAt', 'Modifie le')->hideOnForm()->onlyOnDetail();
    }
}
