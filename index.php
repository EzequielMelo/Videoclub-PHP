<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
</head>

<body>
  <?php

  use LDAP\Result;

  if (isset($_REQUEST["action"])) {
    $action = $_REQUEST["action"];
  } else {
    $action = "mostrarListaPeliculas";
  };

  $pelis = new peliculas();
  $pelis->$action();

  class peliculas
  {
    private $db = null;

    public function __construct()
    {
      $this->db = new mysqli("localhost", "root", "", "videoclub");
    }

    public function mostrarListaPeliculas()
    {
      echo "<h1>Peliculas</h1>";

      if ($result = $this->db->query("SELECT * FROM peliculas
      INNER JOIN actuan ON peliculas.id = actuan.idPelicula
      INNER JOIN personas ON actuan.idPersona = personas.id
      ORDER BY peliculas.titulo")) {
        if ($result->num_rows != 0) {
          echo "<form action='index.php'>
          <input type='hidden' name='action' value='buscarLibros'>
          <input type='text' name='textoBusqueda'>
          <input type='submit' value='Buscar'>
          </form><br>";

          echo "<table border ='1'>";
          while ($fila = $result->fetch_object()) {
            echo "<tr>";
            echo "<td>" . $fila->idPelicula . "</td>";
            echo "<td>" . $fila->titulo . "</td>";
            echo "<td>" . $fila->genero . "</td>";
            echo "<td>" . $fila->pais . "</td>";
            echo "<td>" . $fila->nombre . "</td>";
            echo "<td>" . $fila->apellidos . "</td>";
            echo "<td><img src='./assets/" . $fila->cartel . "'" . "width='100px;'></td>";
            echo "<td><a href='index.php?action=formularioModificarPelicula&idPelicula=" . $fila->idPelicula . "'>Modificar</a></td>";
            echo "<td><a href='index.php?action=borrarPelicula&idPelicula=" . $fila->idPelicula . "'>Borrar</a></td>";
            echo "</tr>";
          }

          echo "</table>";
        } else {
          echo "No se encontraron datos";
        }
      } else {
        echo "Error al tratar de recuperar los datos de la base de datos. Por favor, inténtelo más tarde";
      }
      echo "<p><a href='index.php?action=formularioInsertarPeliculas'>Nuevo</a></p>";
    }

    // --------------------------------- FORMULARIO ALTA DE PELICULAS ----------------------------------------

    public function formularioInsertarPeliculas()
    {
      echo "<h1>formulario insertar peliculas</h1>";

      echo "<form action='index.php' method='post' enctype='multipart/form-data'>
      Título:<input type='text' name='titulo'><br>
      Género:<input type='text' name='genero'><br>
      País:<input type='text' name='pais'><br>
      Año:<input type='text' name='anio'><br>
      Cartel:<input type='file' name='cartel' accept='image/*', /><br>";

      $result = $this->db->query("SELECT * FROM personas");

      echo "Actores: <select name='actores[]' multiple='true'><br>";
      while ($fila = $result->fetch_object()) {
        echo "<option value='" . $fila->id . "'>" . $fila->nombre . " " . $fila->apellidos . "</option>";
      }

      echo "</select>";
      echo "<a href='index.php?action=formularioInsertarActores'>Añadir nuevo</a><br>";

      echo "  <input type='hidden' name='action' value='insertarPelicula'>
      <input type='submit'>
      </form>";
      echo "<p><a href='index.php'>Volver</a></p>";
    }

    // --------------------------------- INSERTAR PELICULAS ----------------------------------------

    public function insertarPelicula()
    {
      echo "<h1>Alta de peliculas</h1>";

      $titulo = $_REQUEST["titulo"];
      $genero = $_REQUEST["genero"];
      $pais = $_REQUEST["pais"];
      $anio = $_REQUEST["anio"];
      $actores = $_REQUEST["actores"];

      // Manejo de archivo
      $fileName = '';
      if (isset($_FILES['cartel']) && $_FILES['cartel']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['cartel']['tmp_name'];
        $fileName = $_FILES['cartel']['name'];
        $uploadFileDir = './assets/'; // Define el directorio donde guardar los archivos
        $destPath = $uploadFileDir . $fileName;

        // Mover el archivo al directorio de destino
        if (move_uploaded_file($fileTmpPath, $destPath)) {
          echo "Archivo subido con éxito.<br>";
        } else {
          echo "Error al mover el archivo a su ubicación final.<br>";
        }
      } else {
        echo "No se ha subido ningún archivo o ha ocurrido un error.<br>";
      }

      // Insertar datos en la base de datos
      $query = "INSERT INTO peliculas (titulo, genero, pais, anio, cartel) VALUES ('$titulo', '$genero', '$pais', '$anio', '$fileName')";
      echo $query; // Debugging purpose
      $this->db->query($query);

      if ($this->db->affected_rows == 1) {
        // Si la inserción de la pelicula ha funcionado, continuamos insertando en la tabla "actuan"
        // Tenemos que averiguar qué idPelicula se ha asignado a la pelicula que acabamos de insertar
        $result = $this->db->query("SELECT MAX(id) AS ultimoIdPelicula FROM peliculas");
        $idPelicula = $result->fetch_object()->ultimoIdPelicula;
        // Ya podemos insertar todos los autores junto con la pelicula en "actuan"
        foreach ($actores as $idPersonas) {
          $this->db->query("INSERT INTO actuan(idPelicula, idPersona) VALUES('$idPelicula', '$idPersonas')");
        }
        echo "Pelicula insertada con éxito";
      } else {
        // Si la inserción de la pelicula ha fallado, mostramos mensaje de error
        echo "Ha ocurrido un error al insertar la pelicula. Por favor, inténtelo más tarde.";
      }

      echo "<p><a href='index.php'>Volver</a></p>";
    }

    // --------------------------------- BORRAR PELICULAS ----------------------------------------

    public function borrarPelicula()
    {
      echo "<h1>Borrar peliculas</h1>";

      $this->db->begin_transaction();

      $idPelicula = $_REQUEST["idPelicula"];

      try {
        $result = $this->db->query("SELECT cartel FROM peliculas WHERE id = '$idPelicula'");
        $fileName = $result->fetch_object()->cartel;

        $this->db->query("DELETE FROM peliculas WHERE id = '$idPelicula'");

        $this->db->commit();

        $filePath = './assets/' . $fileName;
        if (file_exists($filePath)) {
          unlink($filePath);
          echo "Archivo y película eliminados con éxito.<br>";
        } else {
          echo "Archivo no encontrado, pero la película ha sido eliminada.<br>";
        }
      } catch (Exception $e) {
        $this->db->rollback();
        echo "Ha ocurrido un error al eliminar la pelicula o sus relaciones.<br>";
      }
      echo "<p><a href='index.php'>Volver</a></p>";
    }

    // --------------------------------- FORMULARIO MODIFICAR PELICULAS ----------------------------------------

    public function formularioModificarPelicula()
    {
      echo "<h1>Modificacion peliculas</h1>";

      $idPelicula = $_REQUEST["idPelicula"];

      $result = $this->db->query("SELECT * FROM peliculas WHERE id = '$idPelicula'");

      $pelicula = $result->fetch_object();

      echo "<form action = 'index.php' method = 'get'>
      <input type='hidden' name='idPelicula' value='$idPelicula'>
              Título:<input type='text' name='titulo' value='$pelicula->titulo'><br>
              Género:<input type='text' name='genero' value='$pelicula->genero'><br>
              País:<input type='text' name='pais' value='$pelicula->pais'><br>
              Año:<input type='text' name='anio' value='$pelicula->anio'><br>
              Cartel:<input type='file' name='cartel' accept='image/*',><br>";
      echo "<td><img src='./assets/" . $pelicula->cartel . "'" . "width='100px;'></td>";

      $todosLosActores = $this->db->query("SELECT * FROM personas");

      $actoresPelicula = $this->db->query("SELECT idPersona FROM actuan WHERE idPelicula = 'idPelicula'");

      $listaActoresPeliculas = array();
      while ($actor = $actoresPelicula->fetch_object()) {
        $listaActoresPeliculas[] = $actor->idPersona;
      }

      echo "Actores: <select name='actores[]' multiple size='3'>";
      while ($fila = $todosLosActores->fetch_object()) {
        if (in_array($fila->idPersona, $listaActoresPeliculas))
          echo "<option value='$fila->idPersona' selected>$fila->nombre $fila->apellido</option>";
        else
          echo "<option value='$fila->idPersona'>$fila->nombre $fila->apellido</option>";
      }
      echo "</select>";

      echo "<a href='index.php?action=formularioInsertarActores'>Añadir nuevo</a><br>";

      echo "  <input type='hidden' name='action' value='modificarPelicula'>
                          <input type='submit'>
                        </form>";
      echo "<p><a href='index.php'>Volver</a></p>";
    }

    // --------------------------------- MODIFICAR PELICULAS ----------------------------------------

    public function modificarPelicula()
    {
      echo "<h1>Modificacion de peliculas</h1>";

      $idPelicula = $_REQUEST["idPelicula"];
      $titulo = $_REQUEST["titulo"];
      $genero = $_REQUEST["genero"];
      $pais = $_REQUEST["pais"];
      $ano = $_REQUEST["anio"];
      $actores = $_REQUEST["actores"];

      // Manejo de archivo
      $newFileName = '';
      $fileUploaded = false;

      if (isset($_FILES['cartel']) && $_FILES['cartel']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['cartel']['tmp_name'];
        $newFileName = $_FILES['cartel']['name'];
        $uploadFileDir = './assets/'; // Define el directorio donde guardar los archivos
        $destPath = $uploadFileDir . $newFileName;

        // Mover el archivo al directorio de destino
        if (move_uploaded_file($fileTmpPath, $destPath)) {
          echo "Archivo subido con éxito.<br>";
          $result = $this->db->query("SELECT cartel FROM peliculas WHERE id = '$idPelicula'");
          $fileToDeleteName = $result->fetch_object()->cartel;

          $filePath = './assets/' . $fileToDeleteName;
          if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
            echo "Archivo antiguo eliminado con éxito.<br>";
          } else {
            echo "Archivo antiguo no encontrado.<br>";
          }
          $fileUploaded = true;
        } else {
          echo "Error al mover el archivo a su ubicación final.<br>";
        }
      } else {
        echo "No se ha subido ningún archivo o ha ocurrido un error.<br>";
      }

      // Actualizar los datos de la película en la base de datos
      if ($fileUploaded) {
        // Actualizar incluyendo el nuevo archivo
        $this->db->query("UPDATE peliculas SET
        titulo = '$titulo',
        genero = '$genero',
        pais = '$pais',
        anio = '$ano',
        cartel = '$newFileName'
        WHERE id = '$idPelicula'");
      } else {
        // Actualizar sin cambiar el archivo
        $this->db->query("UPDATE peliculas SET
        titulo = '$titulo',
        genero = '$genero',
        pais = '$pais',
        anio = '$ano'
        WHERE id = '$idPelicula'");
      }

      if ($this->db->affected_rows >= 0) {
        $this->db->query("DELETE FROM actuan WHERE idPelicula = '$idPelicula'");
        foreach ($actores as $idActor) {
          $this->db->query("INSERT INTO actuan (idPelicula, idPersona) VALUES('$idPelicula', '$idActor')");
        }
        echo "Pelicula actualizada con éxito.";
      } else {
        echo "Ha ocurrido un error al modificar la pelicula. Por favor intente más tarde.";
      }

      echo "<p><a href='index.php'>Volver</a></p>";
    }
  }
  ?>
</body>

</html>