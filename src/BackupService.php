<?php
namespace FlySkyPie\GoogleDriveBackup;

class BackupService
{
  private $client;
  private $service;
  private $BackupRootFolderId;
  private $SourceRootFolderId;

  /*
   * @param Google_Client $client
   * @param String $BackupRootFolderId
   * @param String $SourceRootFolderId
  */
  function __construct( $client, $BackupRootFolderId, $SourceRootFolderId ) 
  {
    $this->service = new \Google_Service_Drive( $client );
    $this->BackupRootFolderId = $BackupRootFolderId;
    $this->SourceRootFolderId = $SourceRootFolderId;
  }

  /*
   * @todo get files by folderId.
   * @param String $FolderId
   * @var Array
  */
  function getFilesByFolderId( $FolderId )
  {
    $parameters = array();
    $parameters['q'] = "trashed = false AND '$FolderId' IN parents";
    $FilesInFolder = $this->service->files->listFiles( $parameters );
    return $FilesInFolder->getFiles();
  }

  /*
   * @todo get folder by folderId.
   * @param String $FolderId
   * @var Array
  */
  function getFoldersByFolderId( $FolderId )
  {
    $Folders = array();
    $Files = $this->getFilesByFolderId( $FolderId );
    foreach ( $Files as $File )
    {
      if( $File->getMimeType() == "application/vnd.google-apps.folder" )
      $Folders[] = $File;
    }
    return $Folders;
  }

  /*
   * @todo get files by folderId.
   * @param String $FolderId
   * @var Array
  */
  function getFilesByFolderIdWithoutFolder( $FolderId )
  {
    $RealFiles = array();
    $Files = $this->getFilesByFolderId( $FolderId );
    foreach ( $Files as $File )
    {
      if( $File->getMimeType() != "application/vnd.google-apps.folder" )
      $RealFiles[] = $File;
    }
    return $RealFiles;
  }

  /*
   * @todo copy folders and files from source folder to target folder.
   * @param String $SourceFolderId
   * @param String $TargetFolderId
  */
  function copyFolder( $SourceFolderId, $TargetFolderId, $FilePath )
  {
    //report current folder
    $FilePath = $FilePath . '\\' . $this->service->files->get( $SourceFolderId )->getName();
    print 'copying...' . $FilePath . "\n";

    //do copy file where
    $this->copyFile( $SourceFolderId, $TargetFolderId );

    //get sub folder
    $Folders = $this->getFoldersByFolderId( $SourceFolderId );

    foreach( $Folders as $Folder )
    {
      //create new folder under targetfolder
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'name'      => $Folder->getName(),
            'mimeType'  => 'application/vnd.google-apps.folder',
            'parents'   => array( $TargetFolderId ) ));
        $NewFolder = $this->service->files->create($fileMetadata, array(
            'fields' => 'id'));

        $this->copyFolder( $Folder->getId() , $NewFolder->id, $FilePath );
    }
  }

  /*
   * @todo copy files from source folder to target folder.
   * @param String $SourceFolderId
   * @param String $TargetFolderId
  */
  function copyFile( $SourceFolderId, $TargetFolderId )
  {
    //get files under folder
    $originFiles = $this->getFilesByFolderIdWithoutFolder( $SourceFolderId );

    foreach ( $originFiles as $originFile )
    {
      $copiedFile = new \Google_Service_Drive_DriveFile();
      $copiedFile->setName( $originFile->getName() );
      $copiedFile->setParents(array( $TargetFolderId ));

      try {
        $this->service->files->copy( $originFile->getId(), $copiedFile);
      } catch (Exception $e) {
        print "An error occurred: " . $e->getMessage();
      }
    }
  }

  function startBackup()
  {
    //get current date and time for folder name of backup.
    $FolderName = date("Y-m-d h:i:s");

    //create new folder under backup root folder
    $fileMetadata = new \Google_Service_Drive_DriveFile(array(
        'name' => $FolderName,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents' => array( $this->BackupRootFolderId ) ));
    $NewBackupFolder = $this->service->files->create($fileMetadata, array(
        'fields' => 'id'));

    $this->copyFolder( $this->SourceRootFolderId, $NewBackupFolder->id, "" );
  }

}