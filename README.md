# light-db-layer
Lightweight php database abstraction layer to work closer to real data.

Idea of this library is to work as close to your data as you can. 
Most performance issues are created by not understanding data that lies in database server.
This is definitely not an ORM, and creates almost no overhead.
You can think of this library as convention wrapper to help organize your code, with helper methods for simple operations.


> Give power back to your database and learn more about your data to write better code.

# Installation

## Symfony 5

Add this configuration to config/services.yaml

    Doctrine\DBAL\Configuration:
    
    Doctrine\DBAL\Connection:
        factory: 'Doctrine\DBAL\DriverManager::getConnection'
        arguments:
            - url: '%env(DATABASE_URL)%'
              driverOptions: {20: false} # emulate prepared statements
            - '@Doctrine\DBAL\Configuration'
            
In your env files add

    DATABASE_URL=mysql://user:password@server:port/table?serverVersion=8.0&charset=utf8
    
# Usage
## Entity example
    namespace App\Entity;
    
    use App\Entity;
    use Brick\Money\Money;
    
    class FacilityMeal extends Entity
    {
        public int $facilityId;
        public int $mealTypeId;
        public Money $expectedCost;
        public \DateTime $date;
    
        public function expectedCostFormatter(): string
        {
            return (string)$this->expectedCost->getAmount();
        }
        
        public function dateFormatter(): string
        {
            return $this->date->format('Y-m-d');
        }
    }
Entities are used only for insertions or updates. You can define whatever types you want, make it pure php 7.4 style or use getters/setters.
Formatters will run to transform your data to database manageable format.
You should not define variables that should be managed by your database, for example id or at least make those private just like on creation timestamp.
Entity class and repositories will make naming convention transfer to use CamelCase in php and lowercase underscore in database.

## Repository Example

    class Facility extends Repository
    {
        public function getUserFacilities(int $userId): Statement
        {
            $statement = $this->db->prepare('SELECT `facility`.* 
                FROM `facility` 
                INNER JOIN `user_role` ON `facility`.`id`=`user_role`.`facility_id` WHERE `user_role`.`user_id`=?');
            $statement->execute([$userId]);
            return $statement;
        }
    
    }
Repository is just place for your queries. It's based on doctrine/dbal for future extendability but it's really nothing more than simple PDO.
Repository contains helper methods for:
* insert
* replace
* update
* delete
* batch inserts
* batch updates
* basic id fetching
* range id fetching
* fetching all
* getting enum values
* morphing enums into select field key array
* id searching/unique inserts
* reducer to basic array
* reducer to id array
* field value migration
* unique migration
* array to query parameter helper
* table name helper

    
      


