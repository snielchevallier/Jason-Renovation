<?php

namespace App\Controller\Admin;

use App\Repository\EntrepriseRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntrepriseRepository $entrepriseRepository,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Jason Renovation — Administration')
            ->setLocales(['fr']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        // Entreprise est un singleton : le menu pointe directement sur
        // l'edition de l'unique ligne, pas sur une liste.
        $entreprise = $this->entrepriseRepository->get();
        if (null !== $entreprise) {
            yield MenuItem::linkToUrl(
                'Entreprise',
                'fa fa-building',
                $this->adminUrlGenerator
                    ->setController(EntrepriseCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($entreprise->getId())
                    ->generateUrl(),
            );
        }

        yield MenuItem::linkTo(TvaCrudController::class, 'Taux de TVA', 'fa fa-percent');
        yield MenuItem::linkTo(UniteCrudController::class, 'Unites', 'fa fa-ruler');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-user');
    }
}
