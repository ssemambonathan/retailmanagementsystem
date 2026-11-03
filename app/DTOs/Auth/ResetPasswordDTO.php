<?php declare(strict_types=1);
namespace App\DTOs\Auth;
use Illuminate\Http\Request;
final readonly class ResetPasswordDTO
{
    public function __construct(public string $token, public string $email, public string $newPassword) {}
    public static function fromRequest(Request $request): self
    {
        return new self($request->string('token')->toString(), strtolower(trim($request->string('email')->toString())), $request->string('password')->toString());
    }
}
