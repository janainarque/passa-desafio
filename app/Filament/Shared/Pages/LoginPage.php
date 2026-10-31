<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login;

final class LoginPage extends Login
{
    public function mount(): void
    {
        parent::mount();

        if (! app()->isProduction()) {
            $this->form->fill([
                'email' => 'marina@acme.test',
                'password' => 'password',
                'remember' => true,
            ]);
        }
    }

    public function getHeading(): string
    {
        return 'Painel Administrativo';
    }

    public function getSubheading(): string
    {
        return 'Acesso restrito à administração do Passa.';
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Entrar');
    }
}
