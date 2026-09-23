# Unit Testing - Quick Start Guide

This document provides a quick overview of the unit testing setup for this fork and how to get
started with writing and running tests.

## 🚀 Quick Start

### 1. Install Dependencies
```bash
# Install testing dependencies
composer install --ignore-platform-reqs

# Or use the convenience script
./scripts/run-tests.sh install
```

### 2. Run Tests
```bash
# Run all tests
./scripts/run-tests.sh all

# Or use composer
composer test

# Run only unit tests
./scripts/run-tests.sh unit

# Run only integration tests
./scripts/run-tests.sh integration
```

### 3. Check Test Results
- ✅ **Passing tests**: Everything is working correctly
- ❌ **Failing tests**: Issues that need to be fixed
- ⚠️ **Warnings**: Potential issues or deprecated code

## 📁 Project Structure

```
tests/
├── bootstrap.php           # Test configuration and setup
├── TestCase.php           # Base test class with utilities
├── Unit/                  # Unit tests for individual classes
│   ├── UserTest.php      # Tests for User class
│   └── CategoryTest.php  # Tests for Category class
└── Integration/          # Integration tests
    └── DatabaseDataTest.php
```

## 🧪 What We've Set Up

### Testing Framework
- **PHPUnit 9.5+**: Industry-standard PHP testing framework
- **Mockery**: Advanced mocking library for isolating dependencies
- **Custom TestCase**: Base class with helpful utilities

### Test Categories
1. **Unit Tests** (`tests/Unit/`): Test individual classes in isolation
2. **Integration Tests** (`tests/Integration/`): Test how components work together

### Key Features
- ✅ Automated dependency mocking
- ✅ Database interaction testing
- ✅ Code coverage reporting
- ✅ Custom assertions and utilities
- ✅ Convenient test runner script

## 📝 Example Test

Here's a simple unit test example:

```php
<?php

use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    
    private $user;
    private $mockConnection;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up global configuration to prevent errors
        $GLOBALS['CONFIG'] = [
            'root_id' => 1,
            'database_prefix' => 'odm_',
            'db_prefix' => 'odm_'
        ];
        
        // Create properly mocked database connection
        $this->mockConnection = \Mockery::mock(PDO::class);
        $mockStatement = \Mockery::mock(\PDOStatement::class);
        $mockStatement->shouldReceive('execute')->andReturn(true);
        $mockStatement->shouldReceive('fetch')->andReturn(false);
        $mockStatement->shouldReceive('fetchAll')->andReturn([]);
        $mockStatement->shouldReceive('rowCount')->andReturn(0);
        $this->mockConnection->shouldReceive('prepare')->andReturn($mockStatement);
        
        $this->user = new User(1, $this->mockConnection);
    }

    public function testUserCanBeInstantiated(): void
    {
        $this->assertInstanceOf(User::class, $this->user);
    }

    public function testUserPropertiesCanBeSet(): void
    {
        $this->user->username = 'testuser';
        $this->assertEquals('testuser', $this->user->username);
    }
}
```

## 🔧 Available Commands

The test runner script (`scripts/run-tests.sh`) provides these commands:

| Command | Description |
|---------|-------------|
| `./scripts/run-tests.sh all` | Run all tests |
| `./scripts/run-tests.sh unit` | Run only unit tests |
| `./scripts/run-tests.sh integration` | Run only integration tests |
| `./scripts/run-tests.sh coverage` | Generate code coverage report |
| `./scripts/run-tests.sh class User` | Run tests for User class |
| `./scripts/run-tests.sh file CategoryTest` | Run specific test file |
| `./scripts/run-tests.sh list` | List all available tests |

Or via the Makefile: `make test`, `make test-unit`, `make test-integration`, `make coverage`,
`make test-class CLASS=User`, `make test-file FILE=CategoryTest`, `make test-list`.

## 🐛 Bug Discovery & Fix Example

Our tests discovered and helped fix a real bug! The `Category::getAllCategories()` method had an undefined variable issue when no categories exist:

```php
// Previous buggy code:
foreach ($result as $row) {
    $categoryListArray[] = $row;  // $categoryListArray not initialized!
}
return $categoryListArray;  // Undefined variable when $result is empty
```

**Fixed code**:
```php
$categoryListArray = [];  // ✅ Initialize array before loop
foreach ($result as $row) {
    $categoryListArray[] = $row;
}
return $categoryListArray;  // ✅ Always returns array, never undefined
```

This demonstrates how unit tests help discover and verify fixes for real issues!

## 📊 Current Test Status

This section goes stale fast — it did, which is why it's short now. For the actual current
baseline (pass/fail/error counts, and the known root causes behind any open failures), see
[`CLAUDE.md`](../CLAUDE.md)'s Commands section, or just run:
```bash
./scripts/run-tests.sh all
```
The `Category::getAllCategories()` undefined-variable fix mentioned below is still real and still
in the code — that part of this doc's history is accurate, just not the pass-count snapshot.

## 🎯 Next Steps

### For Developers
1. **Write tests first** (Test-Driven Development)
2. **Run tests before committing** code changes
3. **Add tests for new features** and bug fixes
4. **Maintain test coverage** above 80%

### Recommended Test Patterns
- Test happy paths and edge cases
- Mock external dependencies (database, APIs)
- Use descriptive test method names
- Keep tests simple and focused
- Test one thing at a time

### Adding New Tests
1. Create test file in appropriate directory (`Unit/` or `Integration/`)
2. Extend our base `TestCase` class
3. Use the provided utilities for mocking and assertions
4. Run tests to ensure they pass
5. Commit both code and tests together

## 🔍 Code Coverage

Generate an HTML coverage report:
```bash
./scripts/run-tests.sh coverage
```

Open `coverage/index.html` in your browser to see:
- Which lines of code are tested
- Which branches are covered
- Overall coverage percentage

## 💡 Testing Best Practices

### DO ✅
- Write tests for new features
- Test both success and failure cases
- Use meaningful test names
- Mock external dependencies
- Keep tests fast and reliable

### DON'T ❌
- Test framework code (like PHPUnit itself)
- Write tests that depend on external services
- Make tests depend on each other
- Test private methods directly
- Ignore failing tests

## 🆘 Troubleshooting

### Common Issues
1. **"Class not found"**: Check if class is included in `bootstrap.php`
2. **"Database errors"**: Verify mock setup is correct - ensure both `execute()`, `fetch()`, `fetchAll()`, and `rowCount()` are mocked
3. **"Permission denied"**: Run `chmod +x scripts/run-tests.sh`
4. **"Undefined array key"**: Ensure `$GLOBALS['CONFIG']` includes both `db_prefix` and `database_prefix`

### Getting Help
- Check the test output for specific error messages
- Look at existing tests for examples
- Review the `TestCase.php` utilities
- Consult PHPUnit documentation

## 📚 Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Mockery Documentation](http://docs.mockery.io/)
- [PHP Testing Best Practices](https://phpunit.de/getting-started.html)

## 🎯 Issues Resolved

### ✅ Fixed: "Undefined array key db_prefix" Errors
**Problem**: Tests were showing error messages about missing database configuration keys.

**Solution**: Properly set up `$GLOBALS['CONFIG']` in each test with both `db_prefix` and `database_prefix` keys.

### ✅ Fixed: Category::getAllCategories() Undefined Variable Bug
**Problem**: Method would fail with undefined variable when no categories exist.

**Solution**: Initialize `$categoryListArray = []` before the foreach loop.

### ✅ Fixed: Cluttered Test Output
**Problem**: Tests showed informational messages like "stty:" warnings and "User constructor - User not found" messages.

**Solution**: Enhanced test runner script with output filtering to show only relevant test results.

**Key Learnings**:
- Always initialize global configuration in test setUp() methods
- Mock database connections completely (prepare, execute, fetch, fetchAll, rowCount)
- Use consistent naming for database prefix configurations
- Unit tests are excellent for discovering and verifying bug fixes
- Always initialize variables before using them in loops
- Clean test output improves developer experience and focus
- Use grep filters to remove non-essential informational messages

---

**Happy Testing! 🧪✨**

Remember: Good tests lead to better code, fewer bugs, and more confident deployments!