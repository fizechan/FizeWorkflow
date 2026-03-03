/*
 Navicat Premium Dump SQL

 Source Server         : Local-MySQL
 Source Server Type    : MySQL
 Source Server Version : 80404 (8.4.4)
 Source Host           : 127.0.0.1:3306
 Source Schema         : fz_workflow

 Target Server Type    : MySQL
 Target Server Version : 80404 (8.4.4)
 File Encoding         : 65001

 Date: 03/03/2026 16:54:53
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for rj_workflow_contrast
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_contrast`;
CREATE TABLE `rj_workflow_contrast` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `instance_id` int NOT NULL COMMENT '实例ID',
  `scheme_id` int NOT NULL COMMENT '方案ID',
  `scheme_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '方案类型',
  `extend_relation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '外部关联字段(使用该字段进行外部唯一关联)',
  `action_name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '动作',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '动作内容',
  `display_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '用于显示的JSON值',
  `form_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '保存所有需要的JSON值',
  `extend_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '(未启用)重要扩展信息JSON',
  `is_finish` int DEFAULT '0' COMMENT '提交是否已处理，0-否；1-是；',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `channel_auditing_admin_id` (`instance_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2333 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-实例提交修改内容';

-- ----------------------------
-- Records of rj_workflow_contrast
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_contrast_attach
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_contrast_attach`;
CREATE TABLE `rj_workflow_contrast_attach` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `contrast_id` int NOT NULL COMMENT '实例提交ID',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '附件类型',
  `original_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '文件原名称',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '标题',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'URL',
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '路径',
  `extension` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '扩展名',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序，值大靠前',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '备注',
  `is_delete` int NOT NULL DEFAULT '0' COMMENT '是否删除：0-否；1-是；',
  `extend_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '扩展JSON',
  `create_by` int NOT NULL DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int NOT NULL DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `channel_auditing_base_id` (`contrast_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-实例提交附件';

-- ----------------------------
-- Records of rj_workflow_contrast_attach
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_instance
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_instance`;
CREATE TABLE `rj_workflow_instance` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `scheme_id` int NOT NULL COMMENT '方案ID',
  `scheme_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '方案类型',
  `extend_relation` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '外部关联字段(使用该字段进行外部唯一关联)',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '实例名称',
  `status` int DEFAULT NULL COMMENT '状态:0-执行中;1-已通过; 2-已否决; 3-已退回; 4-已挂起；8-已取消；',
  `is_finish` int DEFAULT '0' COMMENT '工作流是否已结束，0-否；1-是；',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `scheme_type` (`scheme_type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-实例';

-- ----------------------------
-- Records of rj_workflow_instance
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_node
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_node`;
CREATE TABLE `rj_workflow_node` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `scheme_id` int NOT NULL COMMENT '方案ID',
  `level` int NOT NULL DEFAULT '0' COMMENT '层级，越大越往下',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '节点名称',
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '节点逻辑处理类',
  `extend_quota` decimal(10,2) unsigned DEFAULT NULL COMMENT '扩展字段-调岗最高额度',
  `params_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '(未启用)参数JSON',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-节点';

-- ----------------------------
-- Records of rj_workflow_node
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_node_action
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_node_action`;
CREATE TABLE `rj_workflow_node_action` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `node_id` int NOT NULL COMMENT '节点ID',
  `action_type` int NOT NULL COMMENT '动作类型:1-通过；2-否决；3-退回；4-挂起；',
  `action_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '动作名称',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序，值大靠前',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=507 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-节点操作定义';

-- ----------------------------
-- Records of rj_workflow_node_action
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_node_role
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_node_role`;
CREATE TABLE `rj_workflow_node_role` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `node_id` int NOT NULL COMMENT '节点ID',
  `role_id` int NOT NULL COMMENT '角色ID',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-允许进入节点的角色';

-- ----------------------------
-- Records of rj_workflow_node_role
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_operation
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_operation`;
CREATE TABLE `rj_workflow_operation` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `scheme_id` int NOT NULL COMMENT '方案ID',
  `instance_id` int NOT NULL COMMENT '实例ID',
  `contrast_id` int NOT NULL DEFAULT '0' COMMENT '提交ID',
  `user_id` int DEFAULT NULL COMMENT '操作者用户ID，为0表示系统操作，NULL表示未分配',
  `user_extend_id` int DEFAULT NULL COMMENT '审批人外部ID，为0表示系统操作，NULL表示未分配',
  `node_id` int NOT NULL DEFAULT '0' COMMENT '所在节点ID',
  `node_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT '节点名称',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '操作生成时间',
  `distribute_time` timestamp NULL DEFAULT NULL COMMENT '分配任务时时间',
  `action_id` int DEFAULT NULL COMMENT '动作ID(非硬性关联)',
  `action_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT '' COMMENT '执行动作描述',
  `action_type` int NOT NULL DEFAULT '0' COMMENT '执行动作：0-未操作；1-已通过；2-已否决；3-已退回；4-已挂起；5-无需操作; 6-已调度；7-已提交；8-已取消；',
  `action_time` timestamp NULL DEFAULT NULL COMMENT '执行时间',
  `view` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '审批意见',
  `inner_view` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '内部审批意见',
  `back_node` int DEFAULT NULL COMMENT '退回节点',
  `dispatch_reason` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '调度原因',
  `prev_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '之前所有操作组成的JSON',
  `form_json` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '保存所有需要的JSON值',
  `extend_json` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci COMMENT '重要扩展信息JSON',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `instance_id` (`instance_id`) USING BTREE,
  KEY `user_extend_id` (`user_extend_id`,`action_type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5933 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-实例节点操作记录';

-- ----------------------------
-- Records of rj_workflow_operation
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_role
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_role`;
CREATE TABLE `rj_workflow_role` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `pid` int NOT NULL DEFAULT '0' COMMENT '上级角色ID(用于用户指定直接上级ID)',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '角色名称',
  `is_preset` int unsigned NOT NULL DEFAULT '0' COMMENT '是否预设：0-否；1-是；',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-角色';

-- ----------------------------
-- Records of rj_workflow_role
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_scheme
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_scheme`;
CREATE TABLE `rj_workflow_scheme` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '方案类型',
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '方案逻辑处理类',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '方案名称',
  `is_preset` int unsigned NOT NULL DEFAULT '0' COMMENT '是否预设：0-否；1-是；',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-方案';

-- ----------------------------
-- Records of rj_workflow_scheme
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for rj_workflow_user
-- ----------------------------
DROP TABLE IF EXISTS `rj_workflow_user`;
CREATE TABLE `rj_workflow_user` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `pid` int NOT NULL DEFAULT '0' COMMENT '直接上级ID',
  `role_id` int NOT NULL DEFAULT '0' COMMENT '角色ID',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '姓名',
  `extend_id` int NOT NULL COMMENT '外部ID(可以使用此ID与外部账号进行关联)',
  `extend_quota` decimal(10,2) unsigned DEFAULT NULL COMMENT '扩展字段-最高审批额度',
  `create_by` int DEFAULT '0' COMMENT '创建者',
  `create_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_by` int DEFAULT '0' COMMENT '修改者',
  `update_on` timestamp NULL DEFAULT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='工作流-人员';

-- ----------------------------
-- Records of rj_workflow_user
-- ----------------------------
BEGIN;
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
