<?php

namespace InfyOm\AdminLTEPreset;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InfyOm\GeneratorHelpers\LaravelUtils;
use Laravel\Ui\Presets\Preset;
use Symfony\Component\Finder\SplFileInfo;

class AdminLTELocalizedPreset extends Preset
{
    /** @var Command */
    protected $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Update the given package array.
     *
     * @param array $packages
     *
     * @return array
     */
    protected static function updatePackageArray(array $packages)
    {
        return [
            'bootstrap'   => '^4.6.0',
            'jquery'      => '^3.6',
            'popper.js'   => '^1.16.1',
            'admin-lte'   => '3.1.0',
            'sass'        => '^1.15.2',
            'sass-loader' => '^8.0.0',
        ] + $packages;
    }

    public function install()
    {
        static::updatePackages();
        static::updateViteConfiguration();
        static::updateSass();
        static::updateBootstrapping();
        static::removeNodeModules();
    }

    /**
     * Update the Vite configuration.
     *
     * @return void
     */
    protected static function updateViteConfiguration()
    {
        copy(__DIR__.'/../adminlte-stubs/bootstrap/vite.config.js', base_path('vite.config.js'));
    }

    /**
     * Update the Sass files for the application.
     *
     * @return void
     */
    protected static function updateSass()
    {
        (new Filesystem())->ensureDirectoryExists(resource_path('sass'));

        copy(__DIR__.'/../adminlte-stubs/bootstrap/_variables.scss', resource_path('sass/_variables.scss'));
        copy(__DIR__.'/../adminlte-stubs/bootstrap/app.scss', resource_path('sass/app.scss'));
    }

    /**
     * Update the bootstrapping files.
     *
     * @return void
     */
    protected static function updateBootstrapping()
    {
        copy(__DIR__.'/../adminlte-stubs/bootstrap/bootstrap.js', resource_path('js/bootstrap.js'));
        copy(__DIR__.'/../adminlte-stubs/bootstrap/app.js', resource_path('js/app.js'));
    }

    public function installAuth()
    {
        $viewsPath = LaravelUtils::getViewPath();

        $this->ensureDirectoriesExist($viewsPath);

        $this->scaffoldAuth();

        $this->scaffoldController();
    }

    protected function ensureDirectoriesExist($viewsPath)
    {
        if (!file_exists($viewsPath.'layouts')) {
            mkdir($viewsPath.'layouts', 0755, true);
        }

        if (!file_exists($viewsPath.'auth')) {
            mkdir($viewsPath.'auth', 0755, true);
        }

        if (!file_exists($viewsPath.'auth/passwords')) {
            mkdir($viewsPath.'auth/passwords', 0755, true);
        }
    }

    private function addAuthRoutes()
    {
        file_put_contents(
            base_path('routes/web.php'),
            "\nAuth::routes();\n",
            FILE_APPEND
        );
    }

    private function addHomeRoute()
    {
        file_put_contents(
            base_path('routes/web.php'),
            "\nRoute::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');\n",
            FILE_APPEND
        );
    }

    protected function scaffoldController()
    {
        if (!is_dir($directory = app_path('Http/Controllers/Auth'))) {
            mkdir($directory, 0755, true);
        }

        $filesystem = new Filesystem();

        collect($filesystem->allFiles(base_path('vendor/laravel/ui/stubs/Auth')))
            ->each(function (SplFileInfo $file) use ($filesystem) {
                $filesystem->copy(
                    $file->getPathname(),
                    app_path('Http/Controllers/Auth/'.Str::replaceLast('.stub', '.php', $file->getFilename()))
                );
            });
    }

    protected function scaffoldAuth()
    {
        file_put_contents(app_path('Http/Controllers/HomeController.php'), $this->compileHomeControllerStub());

        $this->addHomeRoute();

        $this->addAuthRoutes();

        tap(new Filesystem(), function ($filesystem) {
            $filesystem->copyDirectory(__DIR__.'/../adminlte-stubs/auth-localized', resource_path('views/auth'));
            $filesystem->copyDirectory(__DIR__.'/../adminlte-stubs/layouts-localized', resource_path('views/layouts'));
            $filesystem->copy(__DIR__.'/../adminlte-stubs/home-localized.blade.php', resource_path('views/home.blade.php'));

            collect($filesystem->allFiles(base_path('vendor/laravel/ui/stubs/migrations')))
                ->each(function (SplFileInfo $file) use ($filesystem) {
                    $filesystem->copy(
                        $file->getPathname(),
                        database_path('migrations/'.$file->getFilename())
                    );
                });
        });
    }

    protected function compileHomeControllerStub()
    {
        return str_replace(
            '{{namespace}}',
            Container::getInstance()->getNamespace(),
            file_get_contents(base_path('vendor/laravel/ui/src/Auth/stubs/controllers/HomeController.stub'))
        );
    }
}
