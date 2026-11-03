<?php declare(strict_types=1);
namespace App\DTOs\Auth;
use Illuminate\Http\Request;
final readonly class RegisterDTO
{
    public function __construct(public string $firstName, public string $lastName, public string $email, public string $password, public ?string $phone = null, public ?string $role = 'Customer') {}
    public static function fromRequest(Request $request): self
    {
        return new self(trim($request->string('first_name')->toString()), trim($request->string('last_name')->toString()), strtolower(trim($request->string('email')->toString())), $request->string('password')->toString(), $request->input('phone'), $request->input('role','Customer'));
    }
}
