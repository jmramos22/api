<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $curricula = [
            [
                'nombre' => 'Licenciatura en Informática',
                'semestres' => [
                    ['numero' => 1, 'materias' => [
                        'Lógica de programación y pensamiento computacional',
                        'Arquitectura de computadoras',
                        'Matemáticas discretas',
                        'Fundamentos de redes',
                        'Introducción a la informática',
                        'Laboratorio de sistemas operativos',
                    ]],
                    ['numero' => 2, 'materias' => [
                        'Programación estructurada',
                        'Fundamentos de base de datos',
                        'Álgebra lineal',
                        'Redes de computadoras',
                        'Diseño de interfaces de usuarios',
                        'Entornos de desarrollo para programación',
                    ]],
                    ['numero' => 3, 'materias' => [
                        'Estructura de datos',
                        'Manejadores y lenguajes de consulta de datos',
                        'Matemáticas aplicadas',
                        'Administración de redes',
                        'Análisis y diseño de software',
                        'Desarrollo web lado del servidor',
                    ]],
                    ['numero' => 4, 'materias' => [
                        'Paradigmas de programación',
                        'Bases de datos emergentes',
                        'Probabilidad y estadística',
                        'Ciberseguridad',
                        'Desarrollo e implementación de sistemas',
                        'Desarrollo web del lado cliente',
                    ]],
                    ['numero' => 5, 'materias' => [
                        'Cómputo en la nube',
                        'Minería de datos',
                        'Fundamentos de inteligencia artificial',
                        'Introducción a la investigación en informática',
                        'Ingeniería de software',
                        'Pruebas de software',
                    ]],
                    ['numero' => 6, 'materias' => [
                        'Programación para procesamiento de datos',
                        'Innovaciones tecnológicas',
                        'Aprendizaje automático',
                        'Modelos de calidad de software',
                        'Cómputo móvil',
                        'Desarrollo, seguridad y operación de sistemas',
                    ]],
                    ['numero' => 7, 'materias' => [
                        'Administración de recursos tecnológicos',
                        'Emprendimiento tecnológico',
                        'Seminario de proyectos de investigación e intervención',
                        'Comercio electrónico',
                        'Programación paralela y distribuida',
                        'Gestión de seguridad y desempeño de bases de datos',
                    ]],
                ],
            ],
            [
                'nombre' => 'Licenciatura en Ingeniería en Ciencia de Datos',
                'semestres' => [
                    ['numero' => 1, 'materias' => [
                        'Introducción a la Ciencia de Datos',
                        'Laboratorio de Sistemas Operativos',
                        'Matemáticas Discretas',
                        'Pensamiento Computacional e Introducción a la Programación',
                        'Cálculo',
                        'Probabilidad',
                    ]],
                    ['numero' => 2, 'materias' => [
                        'Herramientas de Cómputo para Programación',
                        'Fundamentos de Programación',
                        'Estadística',
                        'Álgebra Lineal',
                        'Fundamentos de Redes',
                        'Bases de Datos',
                    ]],
                    ['numero' => 3, 'materias' => [
                        'Fundamentos de Programación con Lenguajes de Secuencia de Comandos',
                        'Tópicos Espaciales de Estadística',
                        'Paradigmas de Programación Emergentes',
                        'Estructura de Datos',
                        'Investigación de Operaciones',
                        'Modelación Matemática',
                    ]],
                    ['numero' => 4, 'materias' => [
                        'Programación para el Análisis Estadístico',
                        'Teoría de la Computación',
                        'Programación de Servicios',
                        'Métodos Numéricos',
                        'Programación Paralela',
                        'Lenguajes de Consulta de Datos',
                    ]],
                    ['numero' => 5, 'materias' => [
                        'Herramientas de Cómputo en la Nube',
                        'Programación para el Procesamiento y Análisis de Datos',
                        'Inteligencia Artificial',
                        'Programación de Interfaces Web',
                        'Fundamentos de Biología Celular',
                        'Matemáticas Financieras',
                    ]],
                    ['numero' => 6, 'materias' => [
                        'Procesamiento de Datos a Gran Escala',
                        'Aprendizaje Automático',
                        'Seguridad y Gobierno de Datos',
                        'Bases de Datos Analíticas',
                        'Análisis y Diseño de Algoritmos',
                        'Emprendimiento de Base Tecnológica',
                    ]],
                    ['numero' => 7, 'materias' => [
                        'Visualización de Datos y Modelos',
                        'Aprendizaje Profundo',
                        'Ingeniería de Datos',
                        'Optativa I',
                        'Optativa II',
                        'Bases de Datos Emergentes',
                    ]],
                    ['numero' => 8, 'materias' => [
                        'Minería de Datos',
                        'Pruebas de Software',
                        'Ingeniería de Datos',
                        'Optativa III',
                        'Optativa IV',
                        'Introducción a la Investigación en Ciencia de Datos',
                        'Taller Especializado',
                    ]],
                ],
            ],
            [
                // Sin datos de momento, falta investigar el plan de estudios de la carrera de Ingeniería en Sistemas de Información.
                'nombre' => 'Ingeniería en Sistemas de Información',
                'semestres' => [],
            ],
        ];

        foreach ($curricula as $curriculum) {
            DB::table('carreras')->insertOrIgnore([
                'nombre' => $curriculum['nombre'],
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $careerId = DB::table('carreras')
                ->where('nombre', $curriculum['nombre'])
                ->value('id');

            foreach ($curriculum['semestres'] as $semester) {
                DB::table('semestres')->insertOrIgnore([
                    'carrera_id' => $careerId,
                    'numero' => $semester['numero'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $semesterId = DB::table('semestres')
                    ->where('carrera_id', $careerId)
                    ->where('numero', $semester['numero'])
                    ->value('id');

                foreach ($semester['materias'] as $subject) {
                    DB::table('materias')->insertOrIgnore([
                        'semestre_id' => $semesterId,
                        'nombre' => $subject,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
