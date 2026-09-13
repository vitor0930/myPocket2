# SQL: 
CREATE TABLE lancamentos 
( 
 id INT PRIMARY KEY AUTO_INCREMENT,  
 data DATE NOT NULL,  
 valor FLOAT NOT NULL,  
 tipo ENUM ('receita', 'despesa', 'recorrencia'),  
 id_recorrencias INT,  
 id_categorias INT,  
 descricao VARCHAR(255) NOT NULL
); 

CREATE TABLE recorrencias 
( 
 id INT PRIMARY KEY AUTO_INCREMENT,  
 data_inicio DATE NOT NULL,  
 data_final DATE NOT NULL
); 

CREATE TABLE categorias 
( 
 id INT PRIMARY KEY AUTO_INCREMENT,  
 nome VARCHAR(100) NOT NULL
); 

ALTER TABLE lancamentos ADD FOREIGN KEY(id_recorrencias) REFERENCES recorrencias (id);
ALTER TABLE lancamentos ADD FOREIGN KEY(id_categorias) REFERENCES categorias (id);
