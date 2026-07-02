<?php

namespace App\Core\Contracts;

/**
 * Canal de ejecución sobre Hyperliquid (DEX de futuros perpetuos on-chain).
 *
 * Interpretación de las credenciales genéricas del contrato:
 *   - $apiKey    → dirección pública de la wallet principal del usuario (0x...).
 *                  Se usa para las consultas de estado (/info).
 *   - $secretKey → clave privada de la **API wallet (agente)** aprobada por el
 *                  usuario en Hyperliquid. Firma las órdenes (/exchange) pero,
 *                  por diseño del protocolo, NO puede retirar ni transferir
 *                  fondos (las retiradas exigen la firma de la wallet principal).
 */
interface HyperliquidBrokerInterface extends BrokerInterface
{
    //
}
