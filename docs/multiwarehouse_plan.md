Poniżej znajdziesz kompletną koncepcję techniczną w formie samodzielnego bundla dla OroCommerce 6.1.0 CE, zaprojektowanego tak, aby nie modyfikował kodu core i był w pełni aktualizowalny. Projekt opiera się na mechanizmach rozszerzeń Oro (migracje, serwisy, listener’y, dekoratory, layout updates, datagrid’y, ACL, form extensions) i stosuje praktyki zgodne z dokumentacją Oro dotyczące budowy bundli, auto‑rejestracji konfiguracji oraz personalizacji UI i logiki aplikacji doc.oroinc.com, w tym auto‑rejestracji bundli i plików konfiguracyjnych przez Resources/config/oro/bundles.yml oraz konwencje konfiguracji doc.oroinc.com i ogólną strukturę frameworka doc.oroinc.com. Instalacja jako rozszerzenie pozostaje zgodna z mechanizmami dystrybucji Oro/Composer i Marketplace, ale nie wymaga zmian w core doc.oroinc.com.

Nazwa robocza bundla

Vendor: Acme
Bundle: CustomerGroupInventoryBundle
Namespace: Acme\Bundle\CustomerGroupInventoryBundle
Cele biznesowe i logika

Każdy produkt może mieć wiele alternatywnych „stanów magazynowych” przypisanych do konkretnych grup klientów (Customer Groups), opcjonalnie w kontekście witryny (Website).
Na froncie klient widzi stan odpowiedni dla swojej grupy; niezalogowany lub bez przypisania widzi stan domyślny.
Warstwa danych (Doctrine)

Nowa encja: CustomerGroupInventory
id (int, PK)
product (ManyToOne → Oro\Bundle\ProductBundle\Entity\Product, required)
customerGroup (ManyToOne → Oro\Bundle\CustomerBundle\Entity\CustomerGroup, nullable = false dla wariantu „grupowego”; alternatywnie można pozwolić na null i użyć jako „domyślnego”, ale rekomenduję przechowywać domyślny stan w standardowych polach produktu, a w tej encji trzymać wyłącznie nadpisania grupowe)
website (ManyToOne → Oro\Bundle\WebsiteBundle\Entity\Website, nullable = true; umożliwia różne stany per Website; jeśli niewymagane – można pominąć)
quantity (decimal(20,6))
inventoryStatus (string lub enum-like; np. in_stock, out_of_stock, backorder, pre_order)
isActive (boolean)
organization (ManyToOne → Organization; ownership wymagane przez ACL)
createdAt/updatedAt (datetime, lifecycle)
Unikalność: UNIQUE(product_id, customer_group_id, website_id)
Migrations:
Migrations/Schema/v1_0/CreateCustomerGroupInventoryTable.php – utworzenie tabel, FK, indeksów, unikalnego indeksu i ownership metadata (organization).
Repozytorium:
Repository/CustomerGroupInventoryRepository.php:
findOneFor(Product $product, ?CustomerGroup $group, ?Website $website)
findAllForProduct(Product $product) (na potrzeby formularzy)
zapytania zoptymalizowane indeksami (product_id, customer_group_id, website_id)
Warstwa usług (Services)

Provider: CustomerGroupContextResolver
Odpowiada za wydobycie bieżącej Customer Group użytkownika (z tokena bezpieczeństwa, kontekstu strony gościa lub reguł domyślnych).
Provider: CustomerGroupInventoryProvider
API: getResolvedInventory(Product $product, ?Website $website): zwraca DTO z wyliczonym statusem i ilością dla bieżącego użytkownika.
Fallback: jeśli brak rekordu dla grupy/website → zwróć stan domyślny produktu (z core) bez modyfikacji danych w DB.
Cache: pamięć podręczna per (productId, groupId, websiteId) z invalidacją po zmianie encji; użyj oro.cache.abstract.
Decorator (opcjonalnie, jeśli chcesz zastąpić tylko etykietę/statut a nie dokładać nowy blok):
Dekoracja istniejącego providera statusu/availability w Oro (o ile stabilny serviceId jest dostępny w 6.1.0 CE). Jeżeli serviceId w Twojej wersji różni się lub jest niestabilny, zamiast dekoracji użyj layout data provider + update (patrz Frontend).
Manager/Handler: CustomerGroupInventoryManager
Operacje tworzenia/aktualizacji/usuwania wpisów, wykorzystywany w handlerze formularzy i importach.
Event listener’y:
Doctrine event subscriber do invalidacji cache po INSERT/UPDATE/DELETE encji CustomerGroupInventory.
Integracja z backendem (UI admina)

Osobny CRUD dla encji:
Controller/CustomerGroupInventoryController.php (index, create, update, delete)
Form/Type/CustomerGroupInventoryType.php (pola: product, customerGroup, website, quantity, inventoryStatus, isActive)
Datagrid: Resources/config/oro/datagrids.yml
Kolumny: product SKU/name, customerGroup name, website, quantity, inventoryStatus, isActive, updatedAt
Filtry: product, customerGroup, website, status
Routing: Resources/config/oro/routing.yml
ACL: Resources/config/oro/acl.yml (VIEW, CREATE, EDIT, DELETE, zazwyczaj na poziomie klasy i akcji)
Menu / Navigation: Resources/config/oro/menu.yml oraz navigation.yml – pozycja „Customer Group Inventory” w katalogu Products lub Customers.
Integracja z edycją produktu (ergonomia):
Dodaj sekcję/zakładkę w formularzu edycji Produktu (bez modyfikacji core):
Form Extension: Form/Extension/ProductTypeExtension.php – dogrywa pole kolekcji (oro_collection) o nazwie acme_cg_inventory (CollectionType z entry_type = CustomerGroupInventoryType w wersji „bez pola Product”, które ustawiasz w handlerze).
Subskrybent formularza (Form\EventSubscriber) wiąże elementy kolekcji z bieżącym produktem i obsługuje operacje add/remove.
UI Scroll Data listener: Dołożenie sekcji w układzie formularza produktu poprzez zdarzenia UI (np. oro_ui.scroll_data) i dedykowany template sekcji.
Alternatywnie lub dodatkowo: podzakładka w widoku Customer Group z listą przypisań (filter datagrida po danej grupie).
Frontend (produkt – dynamiczne wyświetlanie)

Strategia A – Layout Data Provider + Layout Update (zalecana, stabilna i bez modyfikacji core):
Layout Data Provider:
Layout/DataProvider/CustomerGroupInventoryDataProvider.php
Metoda: get(ProductViewModel|Product, Website): zwraca status/qty/etykietę do wyświetlenia (korzysta z CustomerGroupInventoryProvider).
Layout update dla strony produktu:
Plik: Resources/views/layouts/default/oro_product_frontend_product_view/layout.yml
Akcje:
Dodaj nowy blok z etykietą stanu magazynowego bazującą na data providerze.
Opcjonalnie ukryj lub zastąp istniejący blok ze stanem magazynowym (wymaga znajomości identyfikatora bloku – ustal poprzez debug layoutu w projekcie).
Szablon bloku: Resources/views/layouts/default/oro_product_frontend_product_view/widgets/customer_group_inventory.html.twig
Renderuje różne warianty labeli (np. „Dostępny dla Twojej grupy”, „Brak w magazynie”, „Na zamówienie”, liczba sztuk) na podstawie DTO z providera i ustawień (np. czy pokazywać liczby).
Strategia B – Dekorator provider’a statusu (jeśli chcesz zachować istniejące miejsce wyświetlania bez layout update):
Zarejestruj dekorację serwisu odpowiedzialnego w CE 6.1.0 za dostarczanie inventory status do widoku produktu i w metodzie compute/resolve przestawiaj wynik dla użytkowników należących do grup z przypisanym stanem. Jeśli dekorowany serwis zmieni nazwę lub kontrakt, łatwo to wycofać, bo dekorator żyje w Twoim bundlu.
Walidacja i reguły biznesowe

Unikalność pary (product, group, website) i aktywność wpisu (isActive).
Zasady:
Wyliczanie statusu: jeżeli istnieje aktywny wpis dla danej grupy i (opcjonalnie) website → zwróć jego status/qty; w przeciwnym razie użyj standardowego statusu produktu.
Wspieraj dziedziczenie: jeżeli nie ma wpisu dla group+website, spróbuj group+(website=null); jeżeli brak – fallback do domyślnego produktu.
Constraint’y:
quantity >= 0
inventoryStatus ∈ {in_stock, out_of_stock, backorder, pre_order}
UniqueEntity (product, customerGroup, website)
Bezpieczeństwo (ACL)

Ownership: Organization-owned (najprostsze w zarządzaniu).
acl.yml:
Zasoby klasy: view, create, edit, delete dla encji CustomerGroupInventory.
Controller actions – przypisane do odpowiednich zasobów.
Maski w datagridach i przyciskach zgodnie z ACL.
Wydajność i cache

Cache wyników providera na krótką metę (np. per request i/lub PSR-6 cache z tagowaniem), invalidacja na Doctrine onFlush/postPersist/postUpdate/postRemove encji CustomerGroupInventory.
Indeksy bazy: (product_id, customer_group_id, website_id) klucz unikalny + indeksy wspomagające wyszukiwania per produkt i/lub grupa.
Instalacja i rejestracja bundla

composer.json – pakiet instalowalny via Composer.
Resources/config/oro/bundles.yml – wpis aktywujący bundle w Oro bez modyfikacji plików aplikacji. Oro automatycznie zarejestruje pliki konfiguracji, które przestrzegają konwencji nazw i lokalizacji doc.oroinc.com.
services.yaml – definicje serwisów, tagi (layout.data_provider, doctrine.event_subscriber, form.type, form.type_extension, oro_datagrid.extension, itp.).
Wszystko mieści się w jednym bundlu (standalone), przez co instalacja/odinstalowanie jest bezpieczne i odwracalne. W razie chęci publikacji do Marketplace stosuj przewodnik tworzenia/udostępniania rozszerzeń doc.oroinc.com.
Kluczowe pliki i komponenty (szkic)

AcmeCustomerGroupInventoryBundle.php
Migrations/Schema/v1_0/CreateCustomerGroupInventoryTable.php
Entity/CustomerGroupInventory.php (+ Repository)
Provider/CustomerGroupContextResolver.php
Provider/CustomerGroupInventoryProvider.php
Manager/CustomerGroupInventoryManager.php
EventSubscriber/InventoryCacheInvalidationSubscriber.php
Controller/CustomerGroupInventoryController.php
Form/Type/CustomerGroupInventoryType.php
Form/Extension/ProductTypeExtension.php + Form/EventSubscriber/ProductInventoryCollectionSubscriber.php
Resources/config/oro/datagrids.yml (gridy admina, subgridy per produkt/grupa)
Resources/config/oro/acl.yml, routing.yml, menu.yml, navigation.yml
Resources/views/… (szablony CRUD, partiale sekcji w formularzu produktu)
Layout/DataProvider/CustomerGroupInventoryDataProvider.php
Resources/views/layouts/default/oro_product_frontend_product_view/layout.yml
Resources/views/layouts/default/oro_product_frontend_product_view/widgets/customer_group_inventory.html.twig
translations/messages.pl.yml (etykiety, statusy)
validation/validation.yaml
Przykładowe elementy implementacji (fragmenty)

bundles.yml:
Acme\Bundle\CustomerGroupInventoryBundle\AcmeCustomerGroupInventoryBundle: ~
Dzięki temu bundle i jego konfiguracja zostaną zarejestrowane automatycznie zgodnie z polityką Oro doc.oroinc.com.
Encja (skrócony opis pól i relacji):
product: ManyToOne inversedBy null, joinColumn not null, onDelete CASCADE
customerGroup: ManyToOne not null, onDelete CASCADE
website: ManyToOne nullable, onDelete SET NULL
organization: ManyToOne not null (ACL)
uniqueConstraint: (product_id, customer_group_id, website_id)
Provider (logika wyboru):
Pobierz bieżącą grupę: z tokena CustomerUser → getCustomer() → getGroup()
Repozytorium: najpierw próbuj dopasować group+website, potem group+(null website). Jeśli brak → zwróć domyślny status z core.
Layout update (zarys):
importuj data provider: data.acme_cg_inventory
dodaj blok np. product_view_cg_inventory_label pod sekcją atrybutów produktu
w twig: pobierz DTO z providera i wyświetl label/qty; jeśli brak przypisania grupowego – nic nie rób (pozwalając, by pozostał domyślny komponent) lub pokaż domyślny stan (wg preferencji UX).
Zarządzanie z poziomu Admin (UX)

Globalny grid do przeglądania wszystkich przypisań (bulk actions, masowa edycja opcjonalnie).
Formularz produktu – zakładka „Stany dla grup klientów” z kolekcją wpisów (po jednej linii na Customer Group), walidacja unikalności w czasie rzeczywistym.
Widok Customer Group – subgrid „Product Inventories” filtrowany po tej grupie.
Testy i jakość

Behat (scenariusze UI: widoczność odpowiedniego stanu dla zalogowanego klienta w grupie X, fallback dla gościa).
PHPUnit: testy providera, repozytorium, walidacji, listenerów cache.
Static analysis (PHPStan), CS fixer.
Zgodność i brak ingerencji w core

Projekt nie modyfikuje żadnych plików rdzenia: wykorzystuje bundling, auto‑rejestrację i konwencje Oro, layout updates oraz dekoratory/serwisy doc.oroinc.com, doc.oroinc.com.
Struktura bundla i mechanika publikacji/instalacji są zgodne z wytycznymi deweloperskimi doc.oroinc.com oraz wzorcami „Create a Bundle” doc.oroinc.com.
Uwagi wdrożeniowe

Identyfikatory bloków w layout update: ustal dokładne ID istniejącego bloku „inventory status” w Twojej wersji 6.1.0 CE, korzystając z trybu debug layoutu. Jeśli identyfikator różni się między wersjami/motywami, trzymaj layout update w katalogu konkretnego motywu i zabezpiecz się warunkami (imports/conditions).
Jeżeli zdecydujesz się na dekorację istniejącego providera statusu zamiast layout update, zweryfikuj serviceId i interfejs w 6.1.0 CE, a dekorator zaimplementuj tak, aby w razie braku grupowego wpisu wprost delegował do oryginalnej implementacji.
Co dalej

Mogę dostarczyć startowy szkielet repozytorium (Composer package) wraz z migracją, encją, gridem, formami, layout update i podstawowymi testami, gotowy do instalacji na czystej instancji OroCommerce 6.1.0 CE.

5 citations

Favicon for https://doc.oroinc.com/master/backend/architecture/differences/

Favicon for https://doc.oroinc.com/master/backend/architecture/framework/

Favicon for https://doc.oroinc.com/backend/architecture/framework/architecture-principles/

Favicon for https://doc.oroinc.com/developer/

Favicon for https://doc.oroinc.com/backend/extension/create-bundle/
Differences to Common Symfony Applications - OroCommerce, OroCRM and OroPlatform Documentation
To achieve this, the [OroDistributionBundle](https://github.com/oroinc/platform/tree/master/src/Oro/Bundle/DistributionBundle) leverages Composer and [Packagist](https://packagist.org/). All extensions feature on the [Oro Extensions Store](https://extensions.oroinc.com/orocommerce/). You do not have to use the command line to install extensions unless you want to, and a user with admin permissions can install them on their own in the UI.
Favicon for https://doc.oroinc.com/master/backend/architecture/differences/
doc.oroinc.com
Application Framework - OroCommerce, OroCRM and OroPlatform Documentation
[Oro Application Customization](https://doc.oroinc.com/master/backend/architecture/customization/#architecture-customization-customize) section of the Architecture Guide If you are ready to publish your adjustment in the Oro application for free or paid usage of community members, see the [How to Add an Extension to the OroPlatform Extensions Store](https://doc.oroinc.com/master/backend/extension/add-extension/#dev-cookbook-framework-how-to-add-extension-to-marketplace)
Favicon for https://doc.oroinc.com/master/backend/architecture/framework/
doc.oroinc.com
Architecture Principles of Oro Applications - OroCommerce, OroCRM and OroPlatform Documentation
Oro applications have a lot in common with regular Symfony applications based on the 3rd version of the framework. So, if you are not familiar with Symfony yet - start your acquaintance with the Oro application from the _Getting Started_ and _Guides_ sections of the official [Symfony documentation](https://symfony.com/doc/6.4/index.html). For the bundle to be registered and enabled in Oro applications, it is sufficient to mention the bundle in its _Resources/config/oro/bundles.yml_ file. You can activate any bundle in the application by putting its primary class name in the _Resources/config/oro/bundles.yml_ of your bundle (keep in mind, though, that you must physically install the bundle with the help of Composer). Simultaneously with [bundle auto-registration](https://doc.oroinc.com/backend/architecture/framework/architecture-principles/#dev-guide-application-web-framework-symfony-bundle-system) in the _Resources/config/oro/bundles.yml_ file, the policy of auto-registration of the feature’s configuration files that follow special naming conventions was enabled.
Favicon for https://doc.oroinc.com/backend/architecture/framework/architecture-principles/
doc.oroinc.com
Oro Application Developer Documentation
Developer Documentation is intended for developers and contains information about OroPlatform architecture, guidance on customization and extension of the existing features in Oro applications. Browse the how-to articles to learn about configuration and setup necessary for development or future use of the application. This section extends the rest of the Developer Guide, providing insight into core bundles implementation architecture and infrequently customizable features, which is particularly useful for non-standard customizations for backend and frontend developers as many features are interconnected.
Favicon for https://doc.oroinc.com/developer/
doc.oroinc.com
Create a Bundle - OroCommerce, OroCRM and OroPlatform ...
public function load(array $configs, ContainerBuilder $container) $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
Favicon for https://doc.oroinc.com/backend/extension/create-bundle/
doc.oroinc.com
Previous slideNext slide





Wojciech
dostarcz startowy szkielet






5 hours ago
Favicon for openai
GPT-5
Reasoning (thought for 29.4s)
Poniżej znajdziesz minimalny, gotowy do rozwinięcia „startowy szkielet” niezależnego bundla AcmeCustomerGroupInventoryBundle dla OroCommerce 6.1.0 CE. Zawiera strukturę katalogów, pliki konfiguracyjne, encję, migrację, serwisy, provider’y, formularze, kontroler, datagrid, ACL, layout update i szablony. Po sklonowaniu i dopasowaniu nazw vendora możesz od razu uruchomić migracje i zacząć implementację szczegółów.

Uwaga dot. wyników wyszukiwania: dostępne wyniki to ogólne „skeletony” projektów front‑end i nie dotyczą OroCommerce; traktuj je jako niezwiązane inspiracje do organizacji repozytorium, nie są wykorzystywane w tym bundlu github.com, github.com.

Struktura katalogów
src/Acme/Bundle/CustomerGroupInventoryBundle/AcmeCustomerGroupInventoryBundle.php
src/Acme/Bundle/CustomerGroupInventoryBundle/DependencyInjection/AcmeCustomerGroupInventoryExtension.php
src/Acme/Bundle/CustomerGroupInventoryBundle/DependencyInjection/Configuration.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Entity/CustomerGroupInventory.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Entity/Repository/CustomerGroupInventoryRepository.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Migrations/Schema/v1_0/CreateCustomerGroupInventoryTable.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Provider/CustomerGroupContextResolver.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Provider/CustomerGroupInventoryProvider.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Model/ResolvedInventory.php
src/Acme/Bundle/CustomerGroupInventoryBundle/EventSubscriber/InventoryCacheInvalidationSubscriber.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Controller/CustomerGroupInventoryController.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Form/Type/CustomerGroupInventoryType.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Form/Extension/ProductTypeExtension.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Form/EventSubscriber/ProductInventoryCollectionSubscriber.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Layout/DataProvider/CustomerGroupInventoryDataProvider.php
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/oro/bundles.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/services.yaml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/oro/routing.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/oro/acl.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/oro/datagrids.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/oro/menu.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/oro/navigation.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/views/CustomerGroupInventory/index.html.twig
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/views/CustomerGroupInventory/update.html.twig
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/views/layouts/default/oro_product_frontend_product_view/layout.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/views/layouts/default/oro_product_frontend_product_view/widgets/customer_group_inventory.html.twig
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/translations/messages.pl.yml
src/Acme/Bundle/CustomerGroupInventoryBundle/Resources/config/validation.yml
composer.json
phpstan.neon (opcjonalnie)
README.md
composer.json (pakiet jako symfony-bundle)

{
  "name": "acme/customer-group-inventory-bundle",
  "description": "Customer Group specific inventory for OroCommerce 6.1 CE",
  "type": "symfony-bundle",
  "license": "MIT",
  "require": {
    "php": ">=8.1",
    "oro/commerce": "^6.1",
    "ext-json": "*"
  },
  "autoload": {
    "psr-4": {
      "Acme\\Bundle\\CustomerGroupInventoryBundle\\": "src/Acme/Bundle/CustomerGroupInventoryBundle/"
    }
  },
  "extra": {
    "branch-alias": {
      "dev-main": "1.0-dev"
    }
  }
}
Rejestracja bundla
Resources/config/oro/bundles.yml

bundles:
  - { name: Acme\Bundle\CustomerGroupInventoryBundle\AcmeCustomerGroupInventoryBundle, priority: 50 }
Klasa bundla

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AcmeCustomerGroupInventoryBundle extends Bundle
{
}
DependencyInjection
src/.../DependencyInjection/AcmeCustomerGroupInventoryExtension.php

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AcmeCustomerGroupInventoryExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new \Symfony\Component\DependencyInjection\Loader\YamlFileLoader(
            $container,
            new \Symfony\Component\Config\FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }
}
src/.../DependencyInjection/Configuration.php


<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('acme_customer_group_inventory');
        $rootNode = $treeBuilder->getRootNode();
        // opcjonalne własne opcje
        return $treeBuilder;
    }
}
Services
Resources/config/services.yaml

services:
  _defaults:
    autowire: true
    autoconfigure: false
    public: false

  Acme\Bundle\CustomerGroupInventoryBundle\:
    resource: '../../*'
    exclude: '../../{Entity,Resources,Migrations,Tests}'

  Acme\Bundle\CustomerGroupInventoryBundle\Controller\CustomerGroupInventoryController:
    tags: ['controller.service_arguments']
    public: true

  Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider:
    public: true

  Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupContextResolver: ~

  Acme\Bundle\CustomerGroupInventoryBundle\EventSubscriber\InventoryCacheInvalidationSubscriber:
    tags:
      - { name: doctrine.event_subscriber }

  # Layout data provider
  acme.cg_inventory.layout_data_provider:
    class: Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider\CustomerGroupInventoryDataProvider
    arguments:
      - '@Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider'
      - '@oro_website.manager'
    tags:
      - { name: layout.data_provider, alias: acme_cg_inventory }

  # Form type & extension
  Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType:
    tags: ['form.type']

  Acme\Bundle\CustomerGroupInventoryBundle\Form\Extension\ProductTypeExtension:
    tags:
      - { name: form.type_extension, extended_type: Oro\Bundle\ProductBundle\Form\Type\ProductType }
Migracja DB
Migrations/Schema/v1_0/CreateCustomerGroupInventoryTable.php

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class CreateCustomerGroupInventoryTable implements Migration
{
    public function up(Schema $schema, QueryBag $queries): void
    {
        if ($schema->hasTable('acme_cg_inventory')) {
            return;
        }

        $table = $schema->createTable('acme_cg_inventory');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('product_id', 'integer', ['notnull' => true]);
        $table->addColumn('customer_group_id', 'integer', ['notnull' => true]);
        $table->addColumn('website_id', 'integer', ['notnull' => false]);
        $table->addColumn('organization_id', 'integer', ['notnull' => true]);
        $table->addColumn('quantity', 'decimal', ['precision' => 20, 'scale' => 6, 'default' => 0]);
        $table->addColumn('inventory_status', 'string', ['length' => 32, 'notnull' => true]);
        $table->addColumn('is_active', 'boolean', ['default' => true]);
        $table->addColumn('created_at', 'datetime', []);
        $table->addColumn('updated_at', 'datetime', []);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['product_id','customer_group_id','website_id'], 'uniq_acme_cgi_pcgws');

        $table->addIndex(['product_id'], 'idx_acme_cgi_product');
        $table->addIndex(['customer_group_id'], 'idx_acme_cgi_cg');
        $table->addIndex(['website_id'], 'idx_acme_cgi_ws');

        $table->addForeignKeyConstraint($schema->getTable('oro_product'), ['product_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint($schema->getTable('oro_customer_group'), ['customer_group_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint($schema->getTable('oro_website'), ['website_id'], ['id'], ['onDelete' => 'SET NULL']);
        $table->addForeignKeyConstraint($schema->getTable('oro_organization'), ['organization_id'], ['id'], ['onDelete' => 'CASCADE']);
    }
}
Encja i repozytorium
Entity/CustomerGroupInventory.php

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation as OroConfig;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\ConfigField;

/**
 * @ORM\Entity(repositoryClass="Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository")
 * @ORM\Table(name="acme_cg_inventory", uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"product_id","customer_group_id","website_id"})
 * })
 * @OroConfig\Config(
 *     defaultValues={
 *         "ownership"={
 *             "owner_type"="ORGANIZATION",
 *             "owner_field_name"="organization",
 *             "owner_column_name"="organization_id"
 *         },
 *         "security"={
 *             "type"="ACL",
 *             "group_name"=""
 *         }
 *     }
 * )
 */
class CustomerGroupInventory
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private ?int $id = null;

    /** @ORM\ManyToOne(targetEntity=Product::class) @ORM\JoinColumn(nullable=false, onDelete="CASCADE") */
    private Product $product;

    /** @ORM\ManyToOne(targetEntity=CustomerGroup::class) @ORM\JoinColumn(nullable=false, onDelete="CASCADE") */
    private CustomerGroup $customerGroup;

    /** @ORM\ManyToOne(targetEntity=Website::class) @ORM\JoinColumn(nullable=true, onDelete="SET NULL") */
    private ?Website $website = null;

    /** @ORM\ManyToOne(targetEntity=Organization::class) @ORM\JoinColumn(nullable=false, onDelete="CASCADE") */
    private Organization $organization;

    /** @ORM\Column(type="decimal", precision=20, scale=6) */
    private string $quantity = '0';

    /** @ORM\Column(type="string", length=32) */
    private string $inventoryStatus = 'in_stock';

    /** @ORM\Column(type="boolean") */
    private bool $isActive = true;

    /** @ORM\Column(type="datetime") */
    private \DateTimeInterface $createdAt;

    /** @ORM\Column(type="datetime") */
    private \DateTimeInterface $updatedAt;

    /** @ORM\PrePersist */
    public function prePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /** @ORM\PreUpdate */
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Gettery/settery pominięte dla zwięzłości – dodaj standardowe
}
Entity/Repository/CustomerGroupInventoryRepository.php


<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class CustomerGroupInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerGroupInventory::class);
    }

    public function findOneFor(Product $product, ?CustomerGroup $group, ?Website $website): ?CustomerGroupInventory
    {
        if (!$group) {
            return null;
        }

        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.product = :product')
            ->andWhere('i.customerGroup = :group')
            ->setParameter('product', $product)
            ->setParameter('group', $group)
            ->setMaxResults(1);

        if ($website) {
            $qb2 = clone $qb;
            $result = $qb2->andWhere('i.website = :website')
                ->setParameter('website', $website)
                ->getQuery()->getOneOrNullResult();
            if ($result) {
                return $result;
            }
        }

        return $qb->andWhere('i.website IS NULL')->getQuery()->getOneOrNullResult();
    }
}
Provider’y i model
Model/ResolvedInventory.php

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Model;

class ResolvedInventory
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $quantity,
        public readonly bool $overriddenByGroup
    ) {}
}
Provider/CustomerGroupContextResolver.php


<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CustomerGroupContextResolver
{
    public function __construct(private TokenStorageInterface $tokenStorage) {}

    public function getCurrentCustomerGroup(): ?CustomerGroup
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if ($user instanceof CustomerUser) {
            return $user->getCustomer()?->getGroup();
        }
        return null;
    }
}
Provider/CustomerGroupInventoryProvider.php


<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Provider;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository;
use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;

class CustomerGroupInventoryProvider
{
    private array $localCache = [];

    public function __construct(
        private CustomerGroupInventoryRepository $repo,
        private CustomerGroupContextResolver $contextResolver,
        private WebsiteManager $websiteManager
    ) {}

    public function getResolvedInventory(Product $product, ?Website $website = null): ResolvedInventory
    {
        $website = $website ?: $this->websiteManager->getCurrentWebsite();
        $group = $this->contextResolver->getCurrentCustomerGroup();

        $key = implode(':', [
            $product->getId(),
            $group?->getId() ?? 0,
            $website?->getId() ?? 0
        ]);

        if (isset($this->localCache[$key])) {
            return $this->localCache[$key];
        }

        $override = $this->repo->findOneFor($product, $group, $website);
        if ($override && $override->getIsActive()) {
            return $this->localCache[$key] = new ResolvedInventory(
                $override->getInventoryStatus(),
                $override->getQuantity(),
                true
            );
        }

        // Fallback do domyślnego statusu produktu – dopasuj do Twojej logiki lub provider’a stocku
        $defaultStatus = method_exists($product, 'getInventoryStatus') ? (string)$product->getInventoryStatus() : 'in_stock';
        $defaultQty = null;

        return $this->localCache[$key] = new ResolvedInventory($defaultStatus, $defaultQty, false);
    }

    public function clearLocalCache(): void
    {
        $this->localCache = [];
    }
}
EventSubscriber/InventoryCacheInvalidationSubscriber.php


<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\EventSubscriber;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class InventoryCacheInvalidationSubscriber implements EventSubscriber
{
    public function __construct(private CustomerGroupInventoryProvider $provider) {}

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postUpdate, Events::postRemove];
    }

    public function postPersist(LifecycleEventArgs $args): void { $this->invalidate($args); }
    public function postUpdate(LifecycleEventArgs $args): void { $this->invalidate($args); }
    public function postRemove(LifecycleEventArgs $args): void { $this->invalidate($args); }

    private function invalidate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof CustomerGroupInventory) {
            $this->provider->clearLocalCache();
        }
    }
}
Layout data provider i layout update
Layout/DataProvider/CustomerGroupInventoryDataProvider.php

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Layout\DataProvider;

use Acme\Bundle\CustomerGroupInventoryBundle\Model\ResolvedInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Provider\CustomerGroupInventoryProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class CustomerGroupInventoryDataProvider
{
    public function __construct(
        private CustomerGroupInventoryProvider $provider
    ) {}

    public function getForProduct(Product $product, ?Website $website = null): ResolvedInventory
    {
        return $this->provider->getResolvedInventory($product, $website);
    }
}
Resources/views/layouts/default/oro_product_frontend_product_view/layout.yml


layout:
  actions:
    - '@setBlockTheme':
        themes:
          - 'widgets/customer_group_inventory.html.twig'

    - '@add':
        id: acme_cg_inventory_label
        parentId: product_view_primary_container
        blockType: container
        options: { vars: { } }

    - '@add':
        id: acme_cg_inventory_label_content
        parentId: acme_cg_inventory_label
        blockType: block
        options:
          vars:
            product: '=data["product"]'
            inventory: '=data["acme_cg_inventory"].getForProduct(data["product"])'
Resources/views/layouts/default/oro_product_frontend_product_view/widgets/customer_group_inventory.html.twig


{% block _acme_cg_inventory_label_content_widget %}
  {% set inv = inventory %}
  {% if inv is not null %}
    <div class="acme-cg-inventory {{ inv.overriddenByGroup ? 'is-overridden' : 'is-default' }}">
      {% if inv.overriddenByGroup %}
        <span class="label label--group">{{ 'acme.cginventory.overridden'|trans }}</span>
      {% endif %}
      <span class="status">{{ ('acme.cginventory.status.' ~ inv.status)|trans }}</span>
      {% if inv.quantity is not null %}
        <span class="qty">{{ 'acme.cginventory.qty'|trans({ '%qty%': inv.quantity }) }}</span>
      {% endif %}
    </div>
  {% endif %}
{% endblock %}
Formularze i rozszerzenie Produktu (opcjonalna kolekcja w edycji produktu)
Form/Type/CustomerGroupInventoryType.php

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\Type;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerGroupInventoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerGroup', EntityType::class, [
                'class' => CustomerGroup::class,
                'required' => true,
                'label' => 'acme.cginventory.customer_group'
            ])
            ->add('website', EntityType::class, [
                'class' => Website::class,
                'required' => false,
                'label' => 'acme.cginventory.website'
            ])
            ->add('quantity', NumberType::class, [
                'required' => false,
                'label' => 'acme.cginventory.quantity',
                'scale' => 6
            ])
            ->add('inventoryStatus', ChoiceType::class, [
                'required' => true,
                'label' => 'acme.cginventory.status',
                'choices' => [
                    'acme.cginventory.status.in_stock' => 'in_stock',
                    'acme.cginventory.status.out_of_stock' => 'out_of_stock',
                    'acme.cginventory.status.backorder' => 'backorder',
                    'acme.cginventory.status.pre_order' => 'pre_order',
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
                'label' => 'acme.cginventory.is_active'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CustomerGroupInventory::class
        ]);
    }
}
Form/Extension/ProductTypeExtension.php


<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\Extension;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Form\EventSubscriber\ProductInventoryCollectionSubscriber;
use Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class ProductTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('acmeCgInventories', CollectionType::class, [
            'entry_type' => CustomerGroupInventoryType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'mapped' => false, // powiązanie w subskrybencie
            'label' => 'acme.cginventory.collection'
        ]);

        $builder->addEventSubscriber(new ProductInventoryCollectionSubscriber());
    }
}
Form/EventSubscriber/ProductInventoryCollectionSubscriber.php


<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Form\EventSubscriber;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ProductInventoryCollectionSubscriber implements EventSubscriberInterface
{
    public function __construct(private ManagerRegistry $doctrine) {}

    public static function getSubscribedEvents(): array
    {
        return [FormEvents::POST_SUBMIT => 'onPostSubmit'];
    }

    public function onPostSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $product = $event->getData();
        if (!$product instanceof Product) {
            return;
        }

        $items = $form->get('acmeCgInventories')->getData() ?? [];
        $em = $this->doctrine->getManagerForClass(CustomerGroupInventory::class);

        foreach ($items as $item) {
            if ($item instanceof CustomerGroupInventory) {
                $item->setProduct($product);
                $item->setOrganization($product->getOrganization());
                $em->persist($item);
            }
        }
    }
}
Kontroler (CRUD – zarys)
Controller/CustomerGroupInventoryController.php

<?php
namespace Acme\Bundle\CustomerGroupInventoryBundle\Controller;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Acme\Bundle\CustomerGroupInventoryBundle\Form\Type\CustomerGroupInventoryType;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerGroupInventoryController extends AbstractController
{
    /**
     * @AclAncestor("acme_cg_inventory_view")
     */
    public function indexAction(): Response
    {
        return $this->render('@AcmeCustomerGroupInventory/CustomerGroupInventory/index.html.twig');
    }

    /**
     * @AclAncestor("acme_cg_inventory_create")
     */
    public function createAction(Request $request): Response
    {
        $entity = new CustomerGroupInventory();
        return $this->update($entity, $request);
    }

    /**
     * @AclAncestor("acme_cg_inventory_edit")
     */
    public function updateAction(CustomerGroupInventory $entity, Request $request): Response
    {
        return $this->update($entity, $request);
    }

    private function update(CustomerGroupInventory $entity, Request $request): Response
    {
        $form = $this->createForm(CustomerGroupInventoryType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManagerForClass(CustomerGroupInventory::class);
            $em->persist($entity);
            $em->flush();

            $this->addFlash('success', 'Saved');
            return $this->redirectToRoute('acme_cg_inventory_index');
        }

        return $this->render('@AcmeCustomerGroupInventory/CustomerGroupInventory/update.html.twig', [
            'entity' => $entity,
            'form' => $form->createView()
        ]);
    }
}
Routing
Resources/config/oro/routing.yml

acme_cg_inventory:
  resource: '@AcmeCustomerGroupInventoryBundle/Controller/CustomerGroupInventoryController.php'
  type: annotation

acme_cg_inventory_index:
  path: /acme-cg-inventory
  defaults: { _controller: AcmeCustomerGroupInventoryBundle:CustomerGroupInventory:index }
ACL
Resources/config/oro/acl.yml

acme_cg_inventory_view:
  type: entity
  class: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory
  permission: VIEW
  label: 'acme.cginventory.acl.view'

acme_cg_inventory_create:
  type: entity
  class: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory
  permission: CREATE
  label: 'acme.cginventory.acl.create'

acme_cg_inventory_edit:
  type: entity
  class: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory
  permission: EDIT
  label: 'acme.cginventory.acl.edit'

acme_cg_inventory_delete:
  type: entity
  class: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory
  permission: DELETE
  label: 'acme.cginventory.acl.delete'
Datagrid (index)
Resources/config/oro/datagrids.yml

datagrids:
  acme-cg-inventory-grid:
    source:
      type: orm
      query:
        select:
          - i.id
          - p.sku as productSku
          - p.names default_name
          - cg.name as customerGroupName
          - w.name as websiteName
          - i.quantity
          - i.inventoryStatus
          - i.isActive
          - i.updatedAt
        from:
          - { table: Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory, alias: i }
        join:
          left:
            - { join: i.product, alias: p }
            - { join: i.customerGroup, alias: cg }
            - { join: i.website, alias: w }
    columns:
      productSku: { label: 'oro.product.sku.label' }
      customerGroupName: { label: 'oro.customer.customergroup.label' }
      websiteName: { label: 'oro.website.entity_label' }
      quantity: { label: 'acme.cginventory.quantity' }
      inventoryStatus: { label: 'acme.cginventory.status' }
      isActive: { label: 'acme.cginventory.is_active', frontend_type: boolean }
      updatedAt: { label: 'oro.ui.updated_at' }
    properties:
      id: ~
      update_link:
        type: url
        route: acme_cg_inventory_update
        params: [ id ]
    actions:
      update:
        type: navigate
        label: oro.grid.action.update
        link: update_link
        icon: edit
        acl_resource: acme_cg_inventory_edit
    options:
      entityHint: 'Customer Group Inventory'
Menu i nawigacja
Resources/config/oro/menu.yml

menu_config:
  items:
    acme_cg_inventory_menu:
      label: 'acme.cginventory.menu'
      route: acme_cg_inventory_index
      acl_resource: acme_cg_inventory_view
  tree:
    application_menu:
      children:
        products_tab:
          children:
            acme_cg_inventory_menu: ~
Resources/config/oro/navigation.yml


navigation:
  menu_config:
    titles:
      acme_cg_inventory_index: 'acme.cginventory.menu'
Widoki index/update (placeholdery)
Resources/views/CustomerGroupInventory/index.html.twig

{% extends '@OroUI/actions/index.html.twig' %}
{% set gridName = 'acme-cg-inventory-grid' %}
{% block pageHeader %}Customer Group Inventory{% endblock %}
Resources/views/CustomerGroupInventory/update.html.twig


{% extends '@OroUI/actions/update.html.twig' %}
{% block pageHeader %}Customer Group Inventory{% endblock %}
{% block content_data %}
  {{ parent() }}
{% endblock %}
Tłumaczenia (fragment)
Resources/translations/messages.pl.yml

acme:
  cginventory:
    menu: "Stany magazynowe dla grup klientów"
    collection: "Stany dla grup klientów"
    customer_group: "Grupa klientów"
    website: "Witryna"
    quantity: "Ilość"
    status: "Status"
    is_active: "Aktywny"
    overridden: "Stan dla Twojej grupy"
    qty: "Ilość: %qty%"
    status:
      in_stock: "Dostępny"
      out_of_stock: "Niedostępny"
      backorder: "Na zamówienie"
      pre_order: "Przedsprzedaż"
Walidacja
Resources/config/validation.yml

Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory:
  constraints:
    - Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity:
        fields: [ product, customerGroup, website ]
        message: 'Istnieje już wpis dla tej kombinacji produktu, grupy i witryny.'
  properties:
    quantity:
      - Symfony\Component\Validator\Constraints\NotNull: ~
      - Symfony\Component\Validator\Constraints\GreaterThanOrEqual: 0
    inventoryStatus:
      - Symfony\Component\Validator\Constraints\Choice:
          choices: [in_stock, out_of_stock, backorder, pre_order]
Instalacja i uruchomienie
Dodaj paczkę do projektu: composer require acme/customer-group-inventory-bundle
Wyczyść i zbuduj cache: bin/console cache:clear
Załaduj migracje bundla: bin/console oro:migration:load --bundles=AcmeCustomerGroupInventoryBundle --force
Odbuduj zasoby (jeśli dotyczy): bin/console oro:assets:build
Zaloguj się do panelu admin i odszukaj pozycję menu „Stany magazynowe dla grup klientów”.
Uwagi implementacyjne

W layout.yml ustaw właściwe parentId dla bloku na stronie produktu w zależności od motywu; sprawdź identyfikatory bloków w debug layout.
Jeśli chcesz zastąpić core’owy wskaźnik stanu, możesz dodać akcję @remove dla odpowiedniego bloku i wstawić własny.
Rozszerzenie formularza produktu jest opcjonalne; możesz zacząć od osobnego CRUD i dodać integrację później.
Dla pełnej wydajności rozważ PSR-6 cache z tagowaniem i invalidacją per produkt/grupa/witryna.