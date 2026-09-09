<?php

namespace Tests;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /** @noinspection JsonEncodingApiUsageInspection */
    protected function setUp(): void
    {
        parent::setUp();

        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            RefreshDatabaseState::$migrated = true;
        }

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->registerPermissions();

        TestResponse::macro('props', function ($key = null) {
            $props = json_decode(json_encode($this->original->getData()['page']['props']), JSON_OBJECT_AS_ARRAY);

            if ($key) {
                return Arr::get($props, $key);
            }

            return $props;
        });

        TestResponse::macro('assertHasProp', function ($key) {
            Assert::assertTrue(Arr::has($this->props(), $key));

            return $this;
        });

        TestResponse::macro('assertPropValue', function ($key, $value) {
            $this->assertHasProp($key);

            if (is_callable($value)) {
                $value($this->props($key));
            } else {
                Assert::assertEquals($this->props($key), $value);
            }

            return $this;
        });

        TestResponse::macro('assertPropCount', function ($key, $count) {
            $this->assertHasProp($key);

            Assert::assertCount($count, $this->props($key));

            return $this;
        });
    }

    public function makeMeAnAdmin()
    {
        return (new CreateNewUser())->create([
            'name' => 'May',
            'email' => 'admin-test@itmaal.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }
}
