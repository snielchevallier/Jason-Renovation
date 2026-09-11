<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Exception\AdminGuardException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    /**
     * Longueur minimale des mots de passe crees/modifies via le back-office.
     * Recommandation NIST 800-63B : privilegier la longueur a la complexite
     * (pas de regle de composition, pas de rotation forcee).
     */
    private const PASSWORD_MIN_LENGTH = 12;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
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
        yield DateTimeField::new('lastLoginAt', 'Derniere connexion')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');
        yield ChoiceField::new('roles')
            ->setChoices(['Administrateur' => 'ROLE_ADMIN', 'Utilisateur' => 'ROLE_USER'])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges();
        $motDePasse = TextField::new('plainPassword')
            ->setLabel('Mot de passe')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmation du mot de passe'],
                'required' => Crud::PAGE_NEW === $pageName,
            ])
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->onlyOnForms();

        if (Crud::PAGE_EDIT === $pageName) {
            $motDePasse->setHelp('Laisser les deux champs vides pour conserver le mot de passe actuel.');
        } else {
            $motDePasse->setHelp(\sprintf('%d caracteres minimum.', self::PASSWORD_MIN_LENGTH));
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
        $this->guardAgainstLockout($entityInstance);
        $this->hashPassword($entityInstance, isNew: false);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        \assert($entityInstance instanceof User);

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $entityInstance->getId()) {
            throw new AdminGuardException('Impossible de supprimer votre propre compte.');
        }

        if (\in_array('ROLE_ADMIN', $entityInstance->getRoles(), true)
            && $this->userRepository->countUsersWithRole('ROLE_ADMIN') <= 1
        ) {
            throw new AdminGuardException('Impossible de supprimer le dernier administrateur.');
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    /**
     * Empeche de se couper l'acces au back-office : ni se retirer soi-meme le
     * role admin, ni retirer le role au dernier administrateur restant.
     */
    private function guardAgainstLockout(User $user): void
    {
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return; // reste admin, rien a verifier
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            throw new AdminGuardException('Impossible de retirer votre propre role administrateur.');
        }

        if (0 === $this->userRepository->countUsersWithRole('ROLE_ADMIN')) {
            throw new AdminGuardException('Impossible de retirer ce role : il doit rester au moins un administrateur.');
        }
    }

    private function hashPassword(User $user, bool $isNew): void
    {
        $plainPassword = $user->getPlainPassword();

        if (null === $plainPassword || '' === $plainPassword) {
            if ($isNew) {
                throw new AdminGuardException('Le mot de passe est obligatoire a la creation d\'un utilisateur.');
            }

            return; // edition sans changement de mot de passe
        }

        if (mb_strlen($plainPassword) < self::PASSWORD_MIN_LENGTH) {
            throw new AdminGuardException(\sprintf('Le mot de passe doit contenir au moins %d caracteres.', self::PASSWORD_MIN_LENGTH));
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword(null);
    }
}
