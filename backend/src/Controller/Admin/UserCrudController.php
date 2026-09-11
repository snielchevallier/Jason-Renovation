<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['email' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email');
        yield TextField::new('nom');
        yield ChoiceField::new('roles')
            ->setChoices(['Administrateur' => 'ROLE_ADMIN', 'Utilisateur' => 'ROLE_USER'])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges();
        $motDePasse = TextField::new('plainPassword')
            ->setLabel('Mot de passe')
            ->setFormType(PasswordType::class)
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->onlyOnForms();

        if (Crud::PAGE_EDIT === $pageName) {
            $motDePasse->setHelp('Laisser vide pour conserver le mot de passe actuel.');
        }

        yield $motDePasse;
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->hashPassword($entityInstance, isNew: true);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->hashPassword($entityInstance, isNew: false);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword(User $user, bool $isNew): void
    {
        $plainPassword = $user->getPlainPassword();

        if (null === $plainPassword || '' === $plainPassword) {
            if ($isNew) {
                throw new \RuntimeException('Le mot de passe est obligatoire a la creation d\'un utilisateur.');
            }

            return; // edition sans changement de mot de passe
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword(null);
    }
}
