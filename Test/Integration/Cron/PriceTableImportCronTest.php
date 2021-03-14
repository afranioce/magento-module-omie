<?php

declare(strict_types=1);

namespace Omie\Integration\Cron;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\TestFramework\ObjectManager;
use Omie\Sdk\Communication\Client;
use PHPUnit\Framework\TestCase;

class PriceTableImportCronTest extends TestCase
{
    private ?MockHandler $mockHandler;

    private ?PriceTableImportCron $priceTableImportCron;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();

        $objectManager = ObjectManager::getInstance();

        $client = $objectManager->create(Client::class, [
            'config' => [
                'handler' => $this->mockHandler,
            ]
        ]);

        $objectManager->addSharedInstance($client, Client::class);

        $this->priceTableImportCron = $objectManager->get(PriceTableImportCron::class);
    }

    public function testSuccessfulInvokeWithData(): void
    {
        $this->mockHandler->append(new Response(200, [], $this->listPriceTablesWithData()));

        $this->priceTableImportCron->process();

        /** @var CollectionFactory $collectionFactory */
        $collectionFactory = ObjectManager::getInstance()->get(CollectionFactory::class);

        $collection = $collectionFactory->create();

        // TODO validar a lista esperada
        $result = $collection->getData();

        $expected = [];
        $this->assertEquals($expected, $result);
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
