<?php

namespace App\Http\Controllers;

use dacoto\LaravelWizardInstaller\Controllers\InstallFolderController;
use dacoto\LaravelWizardInstaller\Controllers\InstallServerController;
use Illuminate\Routing\Controller;

/**
 * The wizard runs folders -> php-function -> database.
 */
class InstallerController extends Controller {

    public function phpFunctionIndex() {
        if (!(new InstallServerController())->check() || !(new InstallFolderController())->check()) {
            return redirect()->route('LaravelWizardInstaller::install.folders');
        }
        return view('vendor.installer.steps.symlink_basedir_check', [
            'result' => $this->checkSymlink(),
        ]);
    }

    public function checkSymlink(): bool
    {
        return function_exists('symlink');
    }
}
