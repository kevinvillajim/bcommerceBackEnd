<?php

namespace Tests\Unit;

use App\Infrastructure\Services\ChatFilterService;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserStrike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatFilterService $chatFilterService;

    private User $user;

    private int $strikeLimit = 3;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear el servicio de filtrado
        $this->chatFilterService = new ChatFilterService;

        // Crear un usuario de prueba
        $this->user = User::factory()->create([
            'is_blocked' => false,
        ]);
    }

    #[Test]
    public function it_detects_phone_numbers_in_contact_context_but_allows_business_numbers()
    {
        $messages = [
            // BLOQUEADOS AUTOMÁTICAMENTE - Patrones inequívocos de teléfono (sin necesidad de contexto)
            '0963368896' => true,           // Número ecuatoriano sin contexto - DEBE ser bloqueado
            '0987654321' => true,           // Celular ecuatoriano
            '0223456789' => true,           // Teléfono fijo Quito
            '0445678901' => true,           // Teléfono fijo Cuenca
            '+593987654321' => true,        // Internacional Ecuador
            '+593 9 876 5432' => true,      // Internacional con espacios

            // BLOQUEADOS - Solicitudes de contacto sin números (NUEVO)
            'mandame tu facebook y conversamos' => true,
            'mandame tu whatsapp y conversamos por ahi' => true,
            'me encuentro en la av amazonas y colon frente al edificio sucre' => true,
            'pasame tu correo electronico' => true,
            'conversemos por whatsapp' => true,
            'contactame por privado' => true,

            // BLOQUEADOS - Contexto de contacto claro
            'Mi número es 0987654321' => true,
            'Llámame al +593987654321' => true,
            'Contacto: 02-123-4567' => true,
            'Te paso mi whatsapp 0987654321' => true,

            // NO BLOQUEADOS - Contexto de negocio
            'como te va tengo 125k de productos' => false,
            'Tengo 123 productos disponibles' => false,
            'Cuesta 250 dólares' => false,
            'Precio 225 dolares negociable' => false,
            'Demora 30 dias en llegar' => false,
            'Tiene 1000 unidades en stock' => false,
            'Memoria 512 gb disponible' => false,
            'Peso 15 kg aproximadamente' => false,
            'Oferta 20 por ciento descuento' => false,

            // NO BLOQUEADOS - Números largos ambiguos en contexto de negocio
            'Vendo productos, tengo 987654321 unidades' => false,
            'Precio especial 123456789 centavos' => false,

            // NO BLOQUEADOS - Números sin contexto claro
            '14563' => false,

            // NO BLOQUEADOS - Otros casos normales
            'Mensaje normal sin número' => false,
        ];

        foreach ($messages as $message => $shouldDetect) {
            $result = $this->chatFilterService->containsProhibitedContent($message);
            $this->assertEquals($shouldDetect, $result, "Error verificando: '$message'");
        }
    }

    #[Test]
    public function it_detects_contact_requests_without_numbers()
    {
        $contactRequests = [
            // Solicitudes directas de redes sociales - deben ser bloqueadas
            'mandame tu facebook' => true,
            'mandame tu whatsapp' => true,
            'pasame tu correo' => true,
            'enviame tu instagram' => true,
            'comparteme tu telegram' => true,
            'dame tu email' => true,

            // Solicitudes de encuentro/ubicación - deben ser bloqueadas
            'me encuentro en la av amazonas' => true,
            'estoy en el sector la carolina' => true,
            'vivo en la calle bolivar' => true,
            'trabajo en el barrio la floresta' => true,
            'nos vemos en la plaza' => true,
            'mi direccion es sector norte' => true,

            // Evasiones de plataforma - deben ser bloqueadas
            'conversemos por fuera' => true,
            'hablemos por privado' => true,
            'escribeme por directo' => true,
            'contactame por externo' => true,

            // Intercambio directo - deben ser bloqueadas
            'te paso mi facebook' => true,
            'aqui mi whatsapp' => true,
            'este es mi correo' => true,

            // Mensajes normales - NO deben ser bloqueados
            'hola como estas' => false,
            'el producto es excelente' => false,
            'cuanto cuesta' => false,
            'cuando lo entregas' => false,
            'funciona bien' => false,
        ];

        foreach ($contactRequests as $message => $shouldDetect) {
            $result = $this->chatFilterService->containsProhibitedContent($message);
            $this->assertEquals($shouldDetect, $result, "Error verificando solicitud de contacto: '$message'");
        }
    }

    #[Test]
    public function it_always_detects_number_emojis_regardless_of_context()
    {
        $messages = [
            // Emojis numéricos - siempre bloqueados
            'Mi número es 1️⃣2️⃣3️⃣' => true,
            'Tengo 5️⃣ productos' => true, // Incluso en contexto de negocio
            'Precio 1️⃣0️⃣ dolares' => true, // Incluso en contexto de precio

            // Otros emojis - permitidos
            'Mensaje normal sin emojis numéricos' => false,
            'Otros emojis 🙂🙃😊' => false,
            '🪳🐺 Tengo hambre' => false,
            'Vendo productos 😊' => false,
        ];

        foreach ($messages as $message => $shouldDetect) {
            $result = $this->chatFilterService->containsProhibitedContent($message);
            $this->assertEquals($shouldDetect, $result, "Error verificando emojis en: '$message'");
        }
    }

    #[Test]
    public function it_registers_strike_for_prohibited_content()
    {
        // Verificar que inicialmente no hay strikes
        $this->assertEquals(0, UserStrike::where('user_id', $this->user->id)->count());

        // Enviar mensaje con número telefónico
        $this->chatFilterService->containsProhibitedContent(
            'Mi número es 0987654321',
            $this->user->id,
            $this->strikeLimit
        );

        // Verificar que se registró un strike
        $this->assertEquals(1, UserStrike::where('user_id', $this->user->id)->count());
    }

    #[Test]
    public function it_blocks_seller_after_strike_limit()
    {
        // Crear un perfil de vendedor para el usuario
        $seller = Seller::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // Crear mensajes con contenido prohibido
        $prohibitedMessages = [
            'Mi número es 0987654321',
            'Llámame al +593987654321',
            'Contacto: 1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣',
        ];

        // Enviar los mensajes y verificar el bloqueo del vendedor
        foreach ($prohibitedMessages as $index => $message) {
            $this->chatFilterService->containsProhibitedContent(
                $message,
                $this->user->id,
                $this->strikeLimit
            );

            // Recargar el usuario y el vendedor desde la base de datos
            $this->user->refresh();
            $seller->refresh();

            if ($index + 1 >= $this->strikeLimit) {
                // Después del límite, el vendedor debería estar inactivo
                $this->assertEquals('inactive', $seller->status, "El estado del vendedor no cambió después de {$this->strikeLimit} strikes");
                $this->assertTrue($this->user->is_blocked, "El usuario no fue bloqueado después de {$this->strikeLimit} strikes");
            } else {
                // Antes del límite, el vendedor debería seguir activo
                $this->assertEquals('active', $seller->status, 'El vendedor fue desactivado prematuramente');
                $this->assertFalse($this->user->is_blocked, 'El usuario fue bloqueado prematuramente');
            }
        }
    }

    #[Test]
    public function it_censors_prohibited_content_contextually()
    {
        // Debería censurar números ecuatorianos inequívocos SIEMPRE (sin contexto)
        $phoneOnly = '0963368896';
        $censoredPhoneOnly = $this->chatFilterService->censorProhibitedContent($phoneOnly);
        $this->assertStringNotContainsString('0963368896', $censoredPhoneOnly);
        $this->assertStringContainsString('[NÚMERO CENSURADO]', $censoredPhoneOnly);

        // Debería censurar números en contexto de contacto
        $messageWithPhone = 'Mi número es 0987654321, llámame';
        $censoredMessage = $this->chatFilterService->censorProhibitedContent($messageWithPhone);
        $this->assertStringNotContainsString('0987654321', $censoredMessage);
        $this->assertStringContainsString('[NÚMERO CENSURADO]', $censoredMessage);

        // Debería censurar emojis numéricos siempre
        $messageWithEmoji = 'Mi número es 1️⃣2️⃣3️⃣4️⃣';
        $censoredMessage = $this->chatFilterService->censorProhibitedContent($messageWithEmoji);
        $this->assertStringNotContainsString('1️⃣', $censoredMessage);
        $this->assertStringContainsString('⚠️', $censoredMessage);

        // NO debería censurar números ambiguos en contexto de negocio
        $businessMessage = 'Vendo productos, precio 123456789 centavos';
        $censoredBusinessMessage = $this->chatFilterService->censorProhibitedContent($businessMessage);
        $this->assertEquals($businessMessage, $censoredBusinessMessage); // Debería permanecer igual

        $quantityMessage = 'Tengo 987654321 unidades disponibles';
        $censoredQuantityMessage = $this->chatFilterService->censorProhibitedContent($quantityMessage);
        $this->assertEquals($quantityMessage, $censoredQuantityMessage); // Debería permanecer igual
    }

    #[Test]
    public function it_provides_correct_reject_reason()
    {
        // Número ecuatoriano inequívoco - debería rechazar automáticamente
        $phoneMessage = '0963368896';
        $reason = $this->chatFilterService->getRejectReason($phoneMessage);
        $this->assertNotNull($reason);
        $this->assertStringContainsString('contacto', $reason);

        // Solicitud de contacto sin números - debería rechazar
        $contactRequestMessage = 'mandame tu facebook';
        $contactReason = $this->chatFilterService->getRejectReason($contactRequestMessage);
        $this->assertNotNull($contactReason);
        $this->assertStringContainsString('contacto', $contactReason);

        // Contexto de contacto - debería rechazar
        $contextPhoneMessage = 'Mi número es 0987654321';
        $contextReason = $this->chatFilterService->getRejectReason($contextPhoneMessage);
        $this->assertNotNull($contextReason);
        $this->assertStringContainsString('contacto', $contextReason);

        // Emojis numéricos - siempre rechazar
        $emojiMessage = 'Mi número es 1️⃣2️⃣3️⃣';
        $emojiReason = $this->chatFilterService->getRejectReason($emojiMessage);
        $this->assertNotNull($emojiReason);
        $this->assertStringContainsString('emojis numéricos', $emojiReason);

        // Contexto de negocio - NO debería rechazar
        $businessMessage = 'Vendo productos, precio 123456789 centavos';
        $this->assertNull($this->chatFilterService->getRejectReason($businessMessage));

        $quantityMessage = 'Tengo 987654321 unidades en stock';
        $this->assertNull($this->chatFilterService->getRejectReason($quantityMessage));

        // Mensaje normal - NO debería rechazar
        $normalMessage = 'Un mensaje normal';
        $this->assertNull($this->chatFilterService->getRejectReason($normalMessage));
    }
}
