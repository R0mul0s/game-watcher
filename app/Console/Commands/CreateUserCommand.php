<?php

/**
 * Artisan příkaz: založení uživatelského účtu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Accounts\Actions\CreateUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Obálka nad CreateUser. Heslo se zadává skrytě, nikdy jako parametr —
 * zůstalo by v historii příkazové řádky.
 */
class CreateUserCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'games:create-user
        {email : Přihlašovací e-mail}
        {--name= : Zobrazované jméno (výchozí část e-mailu před zavináčem)}
        {--sql : Neukládat, jen vypsat SQL pro produkci (phpMyAdmin)}';

    /**
     * @var string
     */
    protected $description = 'Založí uživatelský účet (registrace v aplikaci není)';

    /**
     * Zeptá se na heslo, ověří vstup a účet uloží nebo vypíše jako SQL.
     */
    public function handle(CreateUser $createUser): int
    {
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: strstr($email, '@', true) ?: $email);
        $password = (string) $this->secret(__('app.users.password_prompt'));
        $toSql = (bool) $this->option('sql');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                // Pro produkci nejde unikátnost ověřit — lokální databáze ji nezná
                'email' => ['required', 'email', ...($toSql ? [] : [Rule::unique('users', 'email')])],
                'password' => ['required', 'string', 'min:'.config('games.auth.min_password_length')],
            ],
            [
                'email.email' => __('app.users.email_invalid'),
                'email.unique' => __('app.users.email_taken'),
                'password.required' => __('app.users.password_too_short', ['min' => config('games.auth.min_password_length')]),
                'password.min' => __('app.users.password_too_short', ['min' => config('games.auth.min_password_length')]),
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ((string) $this->secret(__('app.users.password_confirm_prompt')) !== $password) {
            $this->error(__('app.users.password_mismatch'));

            return self::FAILURE;
        }

        if ($toSql) {
            $this->line($createUser->toSql($name, $email, $password));

            return self::SUCCESS;
        }

        $createUser->handle($name, $email, $password);
        $this->info(__('app.users.created', ['email' => $email]));

        return self::SUCCESS;
    }
}
