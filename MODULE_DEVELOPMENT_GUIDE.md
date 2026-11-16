# Guide to Developing Modules for the WP Addon Plugin

## For the Windsurf AI Assistant

This guide describes the process of developing new modules for the WP Addon Plugin using the Test-Driven Development (TDD) methodology. By following this approach, we guarantee high code quality, reliability, and the absence of bugs.

Fundamental Principles

1. **TDD approach**: Tests first, then code
2. **Functionality checkpoints**: We define the criteria for success in advance
3. **Integration tests**: Verify the operation of the module as a whole
4. **Unit tests**: Test individual components
5. **Clean code**: Delete all temporary files before committing

## Steps for developing a new module

### Step 1: Analyze requirements and define checkpoints

**What to do:**
- Define the functional requirements of the module
- Identify key performance checkpoints
- Define the input data and expected results

**Example of checkpoints for a new module:**
```markdown
- ✅ The module is activated without errors
- ✅ The module class is loaded and initialized
- ✅ The configuration is loaded correctly
- ✅ The main function is performed correctly
- ✅ Error handling works
- ✅ Performance is not degraded
```

Pollutant Code
```php
// Define checkpoints in the comments
// TODO: Define performance checkpoints
```

### Step 2: Creating Integration Tests

**What to do:**
- Create the file `tests/integration/ModuleNameTest.php`
- Write tests that check checkpoints
- Use real services and mocks of WordPress functions
- Tests should fail before the functionality is implemented

**Structure of the integration test:**
```php
<?php
namespace WpAddon\Tests\Integration;

use WpAddon\Tests\TestCase;
use Brain\Monkey;

class ModuleNameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
// Mock configuration
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testModuleIsActivated(): void
    {
// Checkpoint 1: Module activated
        // Checking for the presence of the module in the system
    }

    public function testModuleLoads(): void
    {
// Checkpoint 2: Class is loading
        // Check for the existence of the class and interface
    }

    public function testMainFunctionality(): void
    {
// Checkpoint 3: The main function works
        // Test of the main functionality
    }
}
```

### Step 3: Creating unit tests

**What to do:**
- Create the file `tests/unit/ModuleNameTest.php`
- Test individual methods
- Mock dependencies
- Check edge cases

**Example of a unit test:**
```php
<?php
namespace WpAddon\Tests\Unit;

use WpAddon\Tests\TestCase;
use Brain\Monkey;
use Mockery;

class ModuleNameTest extends TestCase
{
    private $module;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->service = Mockery::mock('ServiceClass');
        $this->module = new ModuleName($this->service);
    }

    public function testProcessData(): void
    {
        // Arrange
        $input = 'test data';
        $expected = 'processed data';

        $this->service->shouldReceive('process')
            ->with($input)
            ->andReturn($expected);

        // Act
        $result = $this->module->processData($input);

        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

### Step 4: Implementing the Functionality

**What to do:**
- Create a module class in `functions/ModuleName.php`
- Implement the `ModuleInterface` interface
- Use the `HookTrait` trait for hooks
- Add checks and error handling

Unit structure
```php
<?php

use WpAddon\Interfaces\ModuleInterface;
use WpAddon\Traits\HookTrait;

class ModuleName implements ModuleInterface
{
    use HookTrait;

    private $optionService;

    public function __construct($optionService)
    {
        $this->optionService = $optionService;
    }

    public function init(): void
    {
// Registering hooks
        $this->addHook('hook_name', [$this, 'callback'], 10);

Email Fetching Test
        if (!$this->optionService->getSetting('module_enabled')) {
            return;
        }

// Initialization of logic
    }

    public function callback(): void
    {
// main logic
        try {
Implementation
        } catch (Exception $e) {
            error_log('ModuleName error: ' . $e->getMessage());
        }
    }
}
```

### Step 5: Registering the module in the system

**What to do:**
- Add the module to `src/Core/Plugin.php` in the `loadModules()` method
- Check that the module loads when the plugin is activated

**Code in Plugin.php:**
```php
private function loadModules(): void
{
// Existing modules...

New Blank Module
    if ($className === 'ModuleName') {
        $module = new $className($this->optionService);
    }
}
```

### Step 6: Testing and Debugging

**What to do:**
- Run tests: `./run-tests.sh`
- Check code coverage
- Fix errors
- Add debug logs if necessary

commands
```bash
# Run all tests
./run-tests.sh

# Running integration tests
./run-tests.sh integration

# Running unit tests
./run-tests.sh unit
```

### Step 7: Cleaning and Documentation

**What to do:**
- Delete temporary files and debug logs
- Update the documentation (README.md, SETTINGS.md)
- Add the module to the list of features

**Readiness checklist:**
- [ ] All tests pass
- [ ] The code complies with PSR-12
- [ ] Documentation has been updated
- [ ] Temporary files have been deleted
- [ ] Performance has been tested

## Example: Developing the AssetMinification module

### Defining checkpoints
- ✅ Plugin activated
- ✅ The AssetMinification class exists
- ✅ Configuration is loading
- ✅ CSS is minified and cached
- ✅ JS is minified and cached
- ✅ Critical CSS is extracted
- ✅ Hooks are registered

### Integration tests
`AssetMinificationRealWorldTest.php` has been created with verification of all checkpoints.

### Unit tests
`AssetMinificationTest.php` has been created with testing of cache file creation.

### Implementation
- Fixed the code in `AssetMinification.php`
- Added minification and caching
- Registration of hooks on `wp_print_styles` and `wp_print_scripts`

### Result
The module is in production and creates minified files with GZip compression.

Important Considerations

1. **Always check the syntax**: `php -l filename.php`
2. **Use type hints**: For parameters and return values
3. **Error handling**: Log exceptions without interrupting the site's operation
4. **Performance**: Avoid heavy operations in hooks
5. **Security**: Validate input data
6. **Compatibility**: Test with different versions of WordPress/PHP

Tools

- **PHPUnit**: For running tests
- **Brain\Monkey**: For mocking WordPress functions
- **Mockery**: For mocking services
- **PHPStan/PHPCS**: For code validation

Contact

When developing new modules, follow this guide to ensure the quality and reliability of the code.
