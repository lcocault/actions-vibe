-- Projet de rattachement et conclusions d'une tâche.

ALTER TABLE task
    ADD COLUMN project     VARCHAR(255) CHECK (project IS NULL OR btrim(project) <> ''),
    ADD COLUMN conclusions TEXT;

CREATE INDEX task_project_idx ON task (project);
