🎮 Serious Games

"Agroverso Craft" es un sandbox inicial para el desarrollo de un Serious Game orientado a la agricultura campesina.
Su propósito es apoyar la transferencia de investigación y la aplicación de tecnologías emergentes en procesos productivos sostenibles, combinando simulación, análisis climático y gestión territorial en un entorno visual interactivo. Es una primera versión funcional dentro del ecosistema Agroverso, enfocado en la innovación rural, la eficiencia productiva y la conservación patrimonial. 
Este prototipo fue desarrollado en 24 horas, el 9 de noviembre de 2025, como parte de Blitz Hackathon. https://mage.lat/


⚙️ Autores:
🧠 Luis Vidal Vidal (programador)

Email:lvidal@lek.cl

Linkedin: https://www.linkedin.com/in/luisvidalvidal/


🧠 Ivan Maureira Butler (asesor científico)

Email:imb.007@gmail.com

Linkedin: https://www.linkedin.com/in/ivan-maureira-butler-11a24763/



⚙️ Instalación y uso 
Requisitos previos
- XAMPP o cualquier servidor local con PHP ≥ 8.0
- Extensión PDO habilitada (para conexión a MySQL)
- Un navegador web moderno (Chrome, Firefox o Edge)


⚙️ Estructura recomendada del proyecto
- Coloca la carpeta completa del repositorio dentro del directorio htdocs de XAMPP:

xampp/htdocs/agroverso_craft/

│

├── api/

│   ├── climate/

│   ├── predios/

│   ├── recommendations/

│   └── core/

│

├── css/

│   └── app.css

├── js/

│   └── app.js

├── views/

│   ├── mockup.html

│   └── predios.html

└── README.md

⚙️ Base de datos (MySQL)
- Inicia phpMyAdmin y crea una base de datos llamada agroverso_craft.
  
- Importa el esquema (si lo tienes) o crea la tabla básica de predios:
CREATE TABLE predios (

  id INT AUTO_INCREMENT PRIMARY KEY,

  user_id INT DEFAULT 1,

  nombre VARCHAR(255),

  direccion VARCHAR(255),

  lat DECIMAL(10,7),

  lng DECIMAL(10,7),

  world_grid JSON NULL,

  avg_tmin FLOAT NULL,

  avg_tmax FLOAT NULL,

  total_prcp_mm FLOAT NULL,

  frost_days_est INT NULL,

  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

);

- Edita el archivo core/db.php con tus credenciales locales:

function db() {

  return new PDO('mysql:host=localhost;dbname=agroverso_craft;charset=utf8','root','');

}


⚙️ Ejecución:
- Inicia Apache y MySQL desde XAMPP.
- Abre el navegador y entra en:
http://localhost/agroverso_craft/views/mockup.html

- Usa el sandbox para pintar tu predio, actualizar clima y ejecutar simulaciones.



⚙️ Funcionalidades principales:
🎨 Sandbox interactivo (pixel-art) para modelar un predio (20×20 tiles)
🌦️ Módulo climático con conexión a Open-Meteo
🧠 Motor de recomendaciones según clima, uso del suelo y métricas productivas
💾 Guardar y cargar mundo (JSON)
⚡ Simulación básica de rendimiento, agua y riesgo de heladas




🪪 Licencia:

El código fuente de **Agroverso Craft** se publica bajo licencia **MIT**.
Los recursos visuales, narrativos y de diseño (gráficos, íconos, textos e imágenes)  
se encuentran protegidos bajo **Creative Commons BY-NC-ND 4.0**  
(Atribución – No Comercial – Sin Derivadas).


Licensed under Creative Commons BY-NC-ND 4.0.
© 2025 Agroverso / Nestte SpA — All rights reserved.

