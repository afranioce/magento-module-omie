<?php

declare(strict_types=1);

namespace Omie\Sdk\Service\Product;

use DateTimeImmutable;
use Magento\Framework\Serialize\Serializer\Json;
use Omie\Sdk\Communication\OmieClientInterface;
use Omie\Sdk\Entity\Product\PriceTable;
use Omie\Sdk\Entity\Product\PriceTableCharacteristics;
use Omie\Sdk\Entity\Product\PriceTableClients;
use Omie\Sdk\Entity\Product\PriceTableCollection;
use Omie\Sdk\Entity\Product\PriceTableProducts;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;

class PriceTableServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var OmieClientInterface|MockObject
     */
    private $client;

    private PriceTableService $priceTableService;

    protected function setUp(): void
    {
        $this->client = $this->createPartialMock(
            OmieClientInterface::class,
            ['call']
        );

        $this->priceTableService = new PriceTableService(
            $this->client,
            new Json()
        );
    }

    /**
     * @dataProvider callDataProvider
     */
    public function testGetList(string $callCallback, PriceTableCollection $expected)
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getBody')
            ->willReturnCallback([$this, $callCallback]);

        $this->client->expects($this->once())
            ->method('call')
            ->willReturn($response);

        $priceTableCollection = $this->priceTableService->getList();

        $this->assertEquals($expected, $priceTableCollection);
    }

    public function callDataProvider(): array
    {
        return [
            'return empty data successfully' => [
                'callCallback' => 'listPriceTablesEmpty',
                'expected' => PriceTableCollection::fromArray([])
            ],
            'return with some data successfully' => [
                'callCallback' => 'listPriceTablesWithData',
                'expected' => PriceTableCollection::fromArray([
                    new PriceTable(
                        344641584,
                        'code2',
                        true,
                        'name2',
                        'ind0092',
                        'CMC',
                        new PriceTableCharacteristics(
                            false,
                            false,
                            false,
                            null,
                            null,
                            0,
                            0
                        ),
                        new PriceTableProducts(
                            '',
                            true
                        ),
                        new PriceTableClients(
                            false,
                            '',
                            '',
                            13418078
                        )
                    ),
                    new PriceTable(
                        344642303,
                        'code3',
                        true,
                        'name3',
                        'ind0093',
                        'PRD',
                        new PriceTableCharacteristics(
                            false,
                            false,
                            false,
                            new DateTimeImmutable('2020-03-04 00:00:00'),
                            new DateTimeImmutable('2020-03-12 00:00:00'),
                            0,
                            0
                        ),
                        new PriceTableProducts(
                            '',
                            true
                        ),
                        new PriceTableClients(
                            true,
                            '',
                            '',
                            0
                        )
                    ),
                ])
            ],
        ];
    }

    public function listPriceTablesEmpty(): string
    {
        return <<<EOT
{
  "nPagina": 1,
  "nTotPaginas": 1,
  "nRegistros": 8,
  "nTotRegistros": 8,
  "listaTabelasPreco": []
}
EOT;
    }

    public function listPriceTablesWithData(): string
    {
        return <<<EOT
{
  "nPagina": 1,
  "nTotPaginas": 1,
  "nRegistros": 8,
  "nTotRegistros": 8,
  "listaTabelasPreco": [
    {
      "cAtiva": "S",
      "cCodIntTabPreco": "ind0092",
      "cCodigo": "code2",
      "cNome": "name2",
      "cOrigem": "CMC",
      "caracteristicas": {
        "cArredPreco": "N",
        "cTemDesconto": "N",
        "cTemValidade": "N",
        "dDtFinal": "",
        "dDtInicial": "",
        "nDescSugerido": 0,
        "nPercDescMax": 0
      },
      "clientes": {
        "cTag": "Teste",
        "cTodosClientes": "N",
        "cUF": "",
        "nCodTag": 13418078
      },
      "info": {
        "cImpAPI": "S",
        "dAlt": "19/06/2017",
        "dInc": "05/05/2017",
        "hAlt": "13:37:55",
        "hInc": "11:07:48",
        "uAlt": "LPEREIRA",
        "uInc": "WEBSERVICE"
      },
      "nCodTabPreco": 344641584,
      "outrasInfo": {
        "nCodOrigTab": -1,
        "nPercAcrescimo": 0,
        "nPercDesconto": 0
      },
      "produtos": {
        "cConteudo": "",
        "cNCM": "",
        "cTodosProdutos": "S",
        "nCodCaract": 0,
        "nCodFamilia": 0,
        "nCodFornec": 333356111
      }
    },
    {
      "cAtiva": "S",
      "cCodIntTabPreco": "ind0093",
      "cCodigo": "code3",
      "cNome": "name3",
      "cOrigem": "PRD",
      "caracteristicas": {
        "cArredPreco": "N",
        "cTemDesconto": "N",
        "cTemValidade": "N",
        "dDtFinal": "04/03/2020",
        "dDtInicial": "12/03/2020",
        "nDescSugerido": 0,
        "nPercDescMax": 0
      },
      "clientes": {
        "cTag": "",
        "cTodosClientes": "S",
        "cUF": "",
        "nCodTag": 0
      },
      "info": {
        "cImpAPI": "S",
        "dAlt": "25/02/2019",
        "dInc": "05/05/2017",
        "hAlt": "11:42:41",
        "hInc": "11:15:01",
        "uAlt": "LPEREIRA",
        "uInc": "WEBSERVICE"
      },
      "nCodTabPreco": 344642303,
      "outrasInfo": {
        "nCodOrigTab": -2,
        "nPercAcrescimo": 0,
        "nPercDesconto": 0
      },
      "produtos": {
        "cConteudo": "",
        "cNCM": "",
        "cTodosProdutos": "S",
        "nCodCaract": 0,
        "nCodFamilia": 0,
        "nCodFornec": 0
      }
    }
  ]
}
EOT;
    }
}
