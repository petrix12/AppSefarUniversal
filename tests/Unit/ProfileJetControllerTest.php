<?php

namespace Tests\Unit;

use App\Http\Controllers\ProfileJetController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProfileJetControllerTest extends TestCase
{
    public function test_user_without_sales_profile_access_receives_standard_profile_view(): void
    {
        $request = Request::create('/user/profile', 'GET');
        $user = new class {
            public function isCliente(): bool
            {
                return false;
            }

            public function canViewSalesProfile(): bool
            {
                return false;
            }
        };

        $request->setUserResolver(fn () => $user);

        $view = (new ProfileJetController())->show($request);

        $this->assertSame('profile.show', $view->name());
    }

    public function test_client_receives_client_profile_view(): void
    {
        $request = Request::create('/user/profile', 'GET');
        $user = new class {
            public function isCliente(): bool
            {
                return true;
            }

            public function canViewSalesProfile(): bool
            {
                return false;
            }
        };

        $request->setUserResolver(fn () => $user);

        $view = (new ProfileJetController())->show($request);

        $this->assertSame('profile.show-client', $view->name());
    }

    public function test_user_with_sales_profile_access_receives_sales_profile_view(): void
    {
        $request = Request::create('/user/profile', 'GET');
        $user = new class {
            public function isCliente(): bool
            {
                return false;
            }

            public function canViewSalesProfile(): bool
            {
                return true;
            }
        };

        $request->setUserResolver(fn () => $user);

        $view = (new ProfileJetController())->show($request);

        $this->assertSame('profile.show-ventas', $view->name());
    }
}
