-- Liste configurable des projets ; la catégorie (Pro/Perso) est portée par le projet.

CREATE TABLE project (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name       VARCHAR(255) NOT NULL UNIQUE CHECK (btrim(name) <> ''),
    category   VARCHAR(10)  NOT NULL CHECK (category IN ('Pro', 'Perso')),
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Reprise des projets existants (texte libre) ; en cas de catégories mixtes, la plus fréquente l'emporte.
INSERT INTO project (name, category)
SELECT project, category
FROM (
    SELECT project, category,
           row_number() OVER (PARTITION BY project ORDER BY count(*) DESC, category) AS rn
    FROM task
    WHERE project IS NOT NULL
    GROUP BY project, category
) ranked
WHERE rn = 1;

UPDATE task t SET category = p.category
FROM project p
WHERE p.name = t.project AND t.category <> p.category;

-- Renommer un projet met à jour ses tâches ; supprimer un projet utilisé est refusé.
ALTER TABLE task
    ADD CONSTRAINT task_project_fk FOREIGN KEY (project) REFERENCES project (name)
    ON UPDATE CASCADE ON DELETE RESTRICT;
