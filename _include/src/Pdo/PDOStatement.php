<?php /** @noinspection PhpMissingParamTypeInspection */
/** @noinspection ReturnTypeCanBeDeclaredInspection */
/** @noinspection PhpParameterNameChangedDuringInheritanceInspection */
/** @noinspection PhpMissingReturnTypeInspection */

/**
 * Forked to fix a bug with PDO::query()
 * @see https://github.com/filisko/pdo-plus for original code
 */

namespace S2\Core\Pdo;

use PDO as NativePdo;
use PDOStatement as NativePdoStatement;

class PDOStatement extends NativePdoStatement
{
    protected NativePdo $pdo;

    /**
     * For binding simulations purposes.
     */
    protected array $bindings = [];

    protected function __construct(NativePdo $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function bindParam(
        $parameter,
        &$variable,
        $data_type = NativePdo::PARAM_STR,
        $length = null,
        $driver_options = null
    ) {
        $this->bindings[$parameter] = $variable;
        return parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function bindValue($parameter, $variable, $data_type = NativePdo::PARAM_STR)
    {
        $this->bindings[$parameter] = $variable;
        return parent::bindValue($parameter, $variable, $data_type);
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function execute($input_parameters = null)
    {
        if (\is_array($input_parameters)) {
            $this->bindings = $input_parameters;
        }

        $statement = $this->addValuesToQuery($this->bindings, $this->queryString);

        $start  = microtime(true);
        $result = parent::execute($input_parameters);
        $this->pdo->addLog($statement, microtime(true) - $start);
        return $result;
    }

    /**
     * @param array  $bindings
     * @param string $query
     * @return string
     */
    public function addValuesToQuery($bindings, $query)
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        $indexed = ($bindings == array_values($bindings));

        foreach ($bindings as $param => $value) {
            $value = (is_numeric($value) or $value === null) ? $value : $this->pdo->quote($value);
            $value = \is_null($value) ? 'null' : $value;
            if ($indexed) {
                $query = preg_replace('/\?/', $value, $query, 1);
            } else {
                $query = str_replace(":$param", $value, $query);
            }
        }

        return $query;
    }
}