<?php declare(strict_types=1);
namespace App\DTOs\Auth;
use Illuminate\Http\Request;
final readonly class LoginDTO
{
    public function __construct(public string $email, public string $password, public ?string $deviceName = null) {}
    public static function fromRequest(Request $request): self
    {
        return new self(strtolower(trim($request->string('email')->toString())), $request->string('password')->toString(), $request->input('device_name'));
    }
}
