pipeline {
    agent any

    environment {
        DOCKERHUB_CREDENTIALS = credentials('dockerhub-cred') 
        DOCKERHUB_USER = "${DOCKERHUB_CREDENTIALS_USR}"
        DOCKERHUB_PASS = "${DOCKERHUB_CREDENTIALS_PSW}"
        IMAGE_NAME = "mlopezcamp/excusas-c3" 
        BRANCH = "master" 
    }

    stages {
        stage('Checkout SCM') {
            steps {
                script {
                    // Clona el repositorio
                    checkout scm

                    // Obtiene los commits desde el remoto
                    sh 'git fetch origin'

                    // Compara cambios entre remoto y local
                    def diff = sh(script: "git diff --name-only HEAD origin/${BRANCH}", returnStdout: true).trim()

                    if (diff == "") {
                        echo "No hay cambios nuevos en GitHub. No se construirá ni subirá imagen."
                        currentBuild.result = 'SUCCESS'
                        // Marca variable para omitir pasos siguientes
                        env.NO_CHANGES = "true"
                    } else {
                        echo "Cambios detectados en GitHub, se procederá con la construcción y despliegue."
                        env.NO_CHANGES = "false"
                    }
                }
            }
        }

        stage('Build Docker Image') {
            when {
                expression { env.NO_CHANGES == "false" }
            }
            steps {
                script {
                    // Genera TAG con fecha y hash corto del último commit
                    def dateTag = sh(script: "date +%Y%m%d", returnStdout: true).trim()
                    def commitTag = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    env.IMAGE_TAG = "${dateTag}-${commitTag}"

                    echo "Construyendo imagen con tag: ${env.IMAGE_TAG}"
                    sh "docker build -t ${IMAGE_NAME}:${IMAGE_TAG} ."
                }
            }
        }

        stage('Login to DockerHub') {
            when {
                expression { env.NO_CHANGES == "false" }
            }
            steps {
                script {
                    echo "Iniciando sesión en DockerHub..."
                    sh "echo ${DOCKERHUB_PASS} | docker login -u ${DOCKERHUB_USER} --password-stdin"
                }
            }
        }

        stage('Push to DockerHub') {
            when {
                expression { env.NO_CHANGES == "false" }
            }
            steps {
                script {
                    echo "Subiendo imagen ${IMAGE_NAME}:${IMAGE_TAG} a DockerHub..."
                    sh "docker push ${IMAGE_NAME}:${IMAGE_TAG}"
                }
            }
        }

        stage('Clean up local images') {
            when {
                expression { env.NO_CHANGES == "false" }
            }
            steps {
                script {
                    echo "Limpiando imágenes locales..."
                    sh "docker rmi ${IMAGE_NAME}:${IMAGE_TAG} || true"
                }
            }
        }
    }

    post {
        always {
            echo "Pipeline finalizado: ${currentBuild.result}"
        }
    }
}
