<?php

namespace dacoto\LaravelWizardInstaller\Controllers;

use dacoto\LaravelWizardInstaller\RequirementChecker;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class InstallFolderController extends Controller
{
    public function __invoke(): View|Factory|Application|RedirectResponse
    {
        if (!(new InstallServerController())->check()) {
            return redirect()->route('LaravelWizardInstaller::install.server');
        }

        return view('installer::steps.folders', [
            'result' => $this->check()
        ]);
    }

    public function check(): bool
    {
        foreach (config('installer.folders') as $check) {
            if (!RequirementChecker::passes($check['check'])) {
                return false;
            }
        }

        return true;
    }
}
