<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class InstallRedirectTest extends DuskTestCase
{

    public function testGameRedirectIfNoEnv()
    {
        if(file_exists(base_path('.env'))) {
            unlink(base_path('.env'));
        }

        $this->browse(function(Browser $browser) {
            $browser->visit('/')
                ->assertPathIs('/install')
                ->assertSeeIn('#header > h1', 'Install');
        });
    }

}
