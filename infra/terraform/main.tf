terraform {
  required_version = ">= 1.6"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.region
}

variable "region" {
  type    = string
  default = "eu-south-2"
}

variable "instance_type" {
  type    = string
  default = "t3.small"
}

variable "ssh_cidr" {
  type        = string
  description = "Tu IP pública en formato x.x.x.x/32"
}

variable "ssh_public_key" {
  type = string
}

data "aws_availability_zones" "disponibles" {
  state = "available"
}

data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"]
  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-*"]
  }
}

resource "aws_vpc" "principal" {
  cidr_block           = "10.20.0.0/16"
  enable_dns_hostnames = true
  tags                 = { Name = "soportix" }
}

resource "aws_internet_gateway" "salida" {
  vpc_id = aws_vpc.principal.id
}

resource "aws_subnet" "publica" {
  vpc_id            = aws_vpc.principal.id
  cidr_block        = "10.20.1.0/24"
  availability_zone = data.aws_availability_zones.disponibles.names[0]
  tags              = { Name = "soportix-publica" }
}

resource "aws_route_table" "publica" {
  vpc_id = aws_vpc.principal.id
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.salida.id
  }
}

resource "aws_route_table_association" "publica" {
  subnet_id      = aws_subnet.publica.id
  route_table_id = aws_route_table.publica.id
}

resource "aws_security_group" "web" {
  name   = "soportix-web"
  vpc_id = aws_vpc.principal.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = [var.ssh_cidr]
  }
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_key_pair" "acceso" {
  key_name   = "soportix"
  public_key = var.ssh_public_key
}

resource "aws_instance" "servidor" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type
  subnet_id              = aws_subnet.publica.id
  vpc_security_group_ids = [aws_security_group.web.id]
  key_name               = aws_key_pair.acceso.key_name
  user_data              = file("${path.module}/cloud-init.sh")

  metadata_options {
    http_tokens = "required"
  }
  root_block_device {
    volume_size = 20
    encrypted   = true
  }
  tags = { Name = "soportix" }
}

resource "aws_eip" "publica" {
  instance = aws_instance.servidor.id
  domain   = "vpc"
  depends_on = [aws_internet_gateway.salida]
}

output "ip_publica" {
  value = aws_eip.publica.public_ip
}
