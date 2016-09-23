<?php

class Arkade_S3_Model_Cms_Wysiwyg_Images_Storage extends Mage_Cms_Model_Wysiwyg_Images_Storage
{
    private $s3Helper = null;

    public function getDirsCollection($path)
    {
        if ($this->getS3Helper()->checkS3Usage()) {
            /** @var Arkade_S3_Model_Core_File_Storage_S3 $storageModel */
            $storageModel = $this->getS3Helper()->getStorageDatabaseModel();
            $subdirectories = $storageModel->getSubdirectories($path);

            foreach ($subdirectories as $directory) {
                $fullPath = rtrim($path, '/') . '/' . $directory['name'];
                if (!file_exists($fullPath)) {
                    mkdir($fullPath, 0777, true);
                }
            }
        }
        return parent::getDirsCollection($path);
    }

    public function getFilesCollection($path, $type = null)
    {
        if ($this->getS3Helper()->checkS3Usage()) {
            /** @var Arkade_S3_Model_Core_File_Storage_S3 $storageModel */
            $storageModel = $this->getS3Helper()->getStorageDatabaseModel();
            $files = $storageModel->getDirectoryFiles($path);

            /** @var Mage_Core_Model_File_Storage_File $fileStorageModel */
            $fileStorageModel = Mage::getModel('core/file_storage_file');
            foreach ($files as $file) {
                $fileStorageModel->saveFile($file);
            }
        }
        return parent::getFilesCollection($path, $type);
    }

    public function resizeFile($source, $keepRatio = true)
    {
        if ($dest = parent::resizeFile($source, $keepRatio)) {
            if ($this->getS3Helper()->checkS3Usage()) {
                /** @var Arkade_S3_Model_Core_File_Storage_S3 $storageModel */
                $storageModel = $this->getS3Helper()->getStorageDatabaseModel();

                $filePath = ltrim(str_replace(Mage::getConfig()->getOptions()->getMediaDir(), '', $dest), DS);

                $storageModel->saveFile($filePath);
            }
        }
        return $dest;
    }

    public function getThumbsPath($filePath = false)
    {
        $mediaRootDir = Mage::getConfig()->getOptions()->getMediaDir();
        $thumbnailDir = $this->getThumbnailRoot();

        if ($filePath && strpos($filePath, $mediaRootDir) === 0) {
            $thumbnailDir .= DS . ltrim(dirname(substr($filePath, strlen($mediaRootDir))), DS);
        }

        return $thumbnailDir;
    }

    /**
     * @return Arkade_S3_Helper_Core_File_Storage_Database
     */
    protected function getS3Helper()
    {
        if (is_null($this->s3Helper)) {
            $this->s3Helper = Mage::helper('arkade_s3/core_file_storage_database');
        }
        return $this->s3Helper;
    }
    
    /**
     * Upload and resize new file
     *
     * @param string $targetPath Target directory
     * @param string $type Type of storage, e.g. image, media etc.
     * @throws Mage_Core_Exception
     * @return array File info Array
     */
    public function uploadFile($targetPath, $type = null)
    {
        $transport = new Varien_Object(array('target_path' => $targetPath, 'type' => $type));
        Mage::dispatchEvent('wysiwyg_images_upload_file', array('transport' => $transport));

        if ($response = $transport->getResponse()) {
            return $response;
        } else {
            return parent::uploadFile($targetPath, $type);
        }
    }
    
    /**
     * Thumbnail URL getter
     * 
     * Overridden to allow another module to generate a thumbnail URL e.g. from a thumbnailing service.
     *
     * @param  string $filePath original file path
     * @param  boolean $checkFile OPTIONAL is it necessary to check file availability
     * @return string | false
     */
    public function getThumbnailUrl($filePath, $checkFile = false)
    {
        $transport = new Varien_Object(array('file_path' => $filePath, $checkFile => $checkFile));
        Mage::dispatchEvent('wysiwyg_images_get_thumbnail_url', array('transport' => $transport));

        if ($url = $transport->getThumbnailUrl()) {
            return $url;
        } else {
            return parent::getThumbnailUrl($filePath, $checkFile);
        }
    }
}
