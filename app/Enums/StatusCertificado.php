<?php

namespace App\Enums;

enum StatusCertificado: string
{
    case ENTREGUE = 'ENTREGUE';
    case APROVADO = 'APROVADO';
    case REPROVADO = 'REPROVADO';
    case APROVADO_COM_RESSALVAS = 'APROVADO_COM_RESSALVAS';
}
